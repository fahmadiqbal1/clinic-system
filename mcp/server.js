/**
 * Aviva HealthCare — MCP Server for Claude Desktop
 *
 * Exposes three tools so the clinic owner can ask Claude Desktop
 * natural-language questions about live clinic data.
 *
 * Setup:
 *   cd mcp && npm install
 *   Add .mcp.json to the project root (already present)
 *   Open Claude Desktop — the "clinic" MCP server will appear
 *
 * Requirements: Node.js 18+, a .env file in the project root
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import mysql from 'mysql2/promise';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

// ── Load .env from project root ──────────────────────────────
const __dirname = dirname(fileURLToPath(import.meta.url));
const envPath   = resolve(__dirname, '../.env');

function loadEnv(path) {
    try {
        const raw = readFileSync(path, 'utf8');
        for (const line of raw.split('\n')) {
            const trimmed = line.trim();
            if (!trimmed || trimmed.startsWith('#')) continue;
            const eq = trimmed.indexOf('=');
            if (eq === -1) continue;
            const key = trimmed.slice(0, eq).trim();
            const val = trimmed.slice(eq + 1).trim().replace(/^["']|["']$/g, '');
            if (!process.env[key]) process.env[key] = val;
        }
    } catch {
        // .env not found — rely on environment variables already set
    }
}
loadEnv(envPath);

// ── MySQL connection helper ───────────────────────────────────
async function getDb() {
    return mysql.createConnection({
        host:     process.env.DB_HOST     || '127.0.0.1',
        port:     parseInt(process.env.DB_PORT || '3306'),
        user:     process.env.DB_USERNAME || 'root',
        password: process.env.DB_PASSWORD || '',
        database: process.env.DB_DATABASE || 'clinic',
    });
}

async function query(sql, params = []) {
    const db = await getDb();
    try {
        const [rows] = await db.execute(sql, params);
        return rows;
    } finally {
        await db.end();
    }
}

// ── MCP Server ───────────────────────────────────────────────
const server = new McpServer({
    name:    'clinic',
    version: '1.0.0',
});

// Tool 1: Today's clinic statistics
server.tool(
    'get_clinic_stats',
    'Get today\'s patient count, revenue, pending procurements, and low-stock items',
    {},
    async () => {
        const today = new Date().toISOString().slice(0, 10);

        const [[patientRow]]     = await Promise.all([query(`SELECT COUNT(*) AS cnt FROM visits WHERE DATE(created_at) = ?`, [today])]);
        const [[revenueRow]]     = await Promise.all([query(`SELECT COALESCE(SUM(total_amount),0) AS total FROM invoices WHERE DATE(created_at) = ? AND status IN ('paid','partial')`, [today])]);
        const [[pendingProcRow]] = await Promise.all([query(`SELECT COUNT(*) AS cnt FROM procurement_requests WHERE status = 'pending'`)]);
        const lowStock           = await query(`SELECT name, current_stock, minimum_stock_level, department FROM inventory_items WHERE current_stock <= minimum_stock_level AND is_active = 1 LIMIT 20`);
        const [[pendingPayRow]]  = await Promise.all([query(`SELECT COUNT(*) AS cnt FROM doctor_payouts WHERE status = 'pending'`)]);

        const stats = {
            date:                today,
            patients_today:      patientRow.cnt,
            revenue_today_pkr:   parseFloat(revenueRow.total).toFixed(2),
            pending_procurements: pendingProcRow.cnt,
            pending_payouts:     pendingPayRow.cnt,
            low_stock_count:     lowStock.length,
            low_stock_items:     lowStock.map(i => `${i.name} (${i.department}): ${i.current_stock}/${i.minimum_stock_level} ${i.unit || ''}`),
        };

        return {
            content: [{ type: 'text', text: JSON.stringify(stats, null, 2) }],
        };
    }
);

// Tool 2: Pending items requiring action
server.tool(
    'get_pending_items',
    'Get all items pending approval or action: procurements, discounts, payouts',
    {},
    async () => {
        const procurements = await query(
            `SELECT pr.id, u.name AS requested_by, pr.created_at, pr.status, pr.notes
             FROM procurement_requests pr
             LEFT JOIN users u ON u.id = pr.requested_by
             WHERE pr.status = 'pending'
             ORDER BY pr.created_at DESC
             LIMIT 20`
        );

        const payouts = await query(
            `SELECT dp.id, u.name AS doctor, dp.amount, dp.period_start, dp.period_end, dp.status
             FROM doctor_payouts dp
             LEFT JOIN users u ON u.id = dp.doctor_id
             WHERE dp.status = 'pending'
             ORDER BY dp.created_at DESC
             LIMIT 10`
        );

        const discounts = await query(
            `SELECT i.id, i.invoice_number, i.discount_requested, i.discount_reason, u.name AS patient
             FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE i.discount_requested > 0 AND i.discount_approved IS NULL
             LIMIT 10`
        );

        return {
            content: [{
                type: 'text',
                text: JSON.stringify({ procurements, payouts, discounts }, null, 2),
            }],
        };
    }
);

// Tool 3: AI model connection status
server.tool(
    'check_ai_status',
    'Check whether the MedGemma AI model is connected and reachable',
    {},
    async () => {
        const rows = await query(
            `SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('medgemma_api_url','medgemma_provider','medgemma_model','medgemma_last_tested_at','medgemma_last_test_result') LIMIT 10`
        ).catch(() => []);

        const settings = Object.fromEntries(rows.map(r => [r.setting_key, r.setting_value]));
        const apiUrl   = settings.medgemma_api_url || process.env.MEDGEMMA_API_URL || 'not configured';

        // Quick reachability ping
        let reachable = false;
        if (apiUrl && apiUrl !== 'not configured') {
            try {
                const pingUrl = apiUrl.replace(/\/$/, '') + '/api/version';
                const res = await fetch(pingUrl, {
                    signal: AbortSignal.timeout(3000),
                    headers: { 'bypass-tunnel-reminder': 'true' },
                });
                reachable = res.ok || res.status === 404;
            } catch {
                reachable = false;
            }
        }

        return {
            content: [{
                type: 'text',
                text: JSON.stringify({
                    reachable,
                    api_url:       apiUrl,
                    provider:      settings.medgemma_provider || 'ollama',
                    model:         settings.medgemma_model    || 'medgemma3:4b',
                    last_tested:   settings.medgemma_last_tested_at  || null,
                    last_result:   settings.medgemma_last_test_result || null,
                }, null, 2),
            }],
        };
    }
);

// ── Start ────────────────────────────────────────────────────
const transport = new StdioServerTransport();
await server.connect(transport);
