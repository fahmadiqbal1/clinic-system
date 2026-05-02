<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\Vendor;
use Illuminate\Console\Command;

class ImportMullerPhippsMedicines extends Command
{
    protected $signature = 'pharmacy:import-muller-phipps
                            {--json= : Path to the extracted JSON file (default: auto-extract from PDF)}
                            {--pdf=  : Path to the Muller & Phipps PDF price list}
                            {--dry-run : Preview without inserting}';

    protected $description = 'Import Muller & Phipps medicine price list into pharmacy inventory (stock=0)';

    public function handle(): int
    {
        // Ensure vendor exists
        $vendor = Vendor::firstOrCreate(
            ['name' => 'Muller & Phipps Pakistan (Pvt) Ltd'],
            [
                'short_name'    => 'M&P',
                'po_email'      => '',
                'payment_terms' => 'Net 30',
                'is_approved'   => true,
                'auto_send_po'  => false,
            ]
        );

        $jsonPath = $this->option('json');
        $pdfPath  = $this->option('pdf');

        if ($jsonPath && file_exists($jsonPath)) {
            $medicines = json_decode(file_get_contents($jsonPath), true);
        } elseif ($pdfPath && file_exists($pdfPath)) {
            $medicines = $this->extractFromPdf($pdfPath);
        } else {
            // Try default download location
            $default = 'C:/Users/94/Downloads/muller_phipps_medicines.json';
            if (file_exists($default)) {
                $medicines = json_decode(file_get_contents($default), true);
            } else {
                $this->error('Provide --json or --pdf path. No default JSON found.');
                return 1;
            }
        }

        if (empty($medicines)) {
            $this->error('No medicines parsed from input.');
            return 1;
        }

        $this->info(sprintf('Parsed %d medicines from price list.', count($medicines)));

        if ($this->option('dry-run')) {
            $this->table(['SKU', 'Name', 'Price'], array_slice($medicines, 0, 20));
            $this->warn('Dry run — nothing inserted.');
            return 0;
        }

        $inserted = 0;
        $updated  = 0;
        $bar      = $this->output->createProgressBar(count($medicines));
        $bar->start();

        foreach ($medicines as $med) {
            $sku   = $med['sku'] ?? null;
            $name  = trim($med['name'] ?? '');
            $price = (float) ($med['price'] ?? 0);

            if (!$name || $price <= 0) {
                $bar->advance();
                continue;
            }

            $existing = $sku ? InventoryItem::where('sku', $sku)->first() : null;

            if ($existing) {
                $existing->update(['selling_price' => $price, 'vendor_id' => $vendor->id]);
                $updated++;
            } else {
                InventoryItem::create([
                    'department'          => 'pharmacy',
                    'name'                => $name,
                    'sku'                 => $sku,
                    'unit'                => 'pack',
                    'minimum_stock_level' => 5,
                    'purchase_price'      => round($price * 0.85, 2),
                    'selling_price'       => $price,
                    'requires_prescription' => false,
                    'is_active'           => true,
                    'vendor_id'           => $vendor->id,
                ]);
                $inserted++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Inserted: {$inserted} | Updated: {$updated}");
        $this->info("Vendor '{$vendor->name}' linked to all items (ID: {$vendor->id}).");

        return 0;
    }

    private function extractFromPdf(string $path): array
    {
        $script = <<<'PYTHON'
import sys, json, re
try:
    import pdfplumber
except ImportError:
    import subprocess; subprocess.check_call([sys.executable, '-m', 'pip', 'install', 'pdfplumber', '-q'])
    import pdfplumber

medicines = []
seen = set()
with pdfplumber.open(sys.argv[1]) as pdf:
    for page in pdf.pages:
        words = page.extract_words(x_tolerance=3, y_tolerance=3)
        rows = {}
        for w in words:
            y = round(w['top'] / 2) * 2
            rows.setdefault(y, []).append(w)
        for y_key in sorted(rows):
            row_words = sorted(rows[y_key], key=lambda w: w['x0'])
            row_text = ' '.join(w['text'] for w in row_words)
            for part in re.split(r'(?=\b\d{5,7}\b)', row_text):
                part = part.strip()
                m = re.match(r'^(\d{5,7})\s+(.+?)\s+([\d,]+\.\d{2})\s*$', part)
                if not m: continue
                sku = m.group(1)
                if sku in seen: continue
                name = re.sub(r'\s+\d+[xX]?\d*\s*[sSmMgGlL]+\s*$', '', m.group(2)).strip()
                price = float(m.group(3).replace(',',''))
                if price <= 0: continue
                seen.add(sku)
                medicines.append({'sku': sku, 'name': name, 'price': price})
print(json.dumps(medicines))
PYTHON;

        $tmpScript = sys_get_temp_dir() . '/mp_extract.py';
        file_put_contents($tmpScript, $script);
        $output = shell_exec("python \"{$tmpScript}\" \"{$path}\" 2>/dev/null");
        @unlink($tmpScript);

        return $output ? json_decode($output, true) ?? [] : [];
    }
}
