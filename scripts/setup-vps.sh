#!/usr/bin/env bash
# Run as root on a fresh Hostinger KVM4 VPS (Ubuntu 24.04 LTS)
set -euo pipefail

DOMAIN="clinic.yourdomain.com"  # REPLACE
APP_DIR="/var/www/clinic-system"
DEPLOY_USER="clinicapp"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

log "=== Aviva Clinic VPS Setup ==="

# System update
apt-get update && apt-get upgrade -y

# Essential tools
apt-get install -y git curl wget unzip ufw fail2ban certbot python3-certbot-nginx \
    htop iotop ncdu logrotate

# Docker
curl -fsSL https://get.docker.com | sh
systemctl enable docker
systemctl start docker

# Docker Compose plugin
apt-get install -y docker-compose-plugin

# Create deploy user
useradd -m -s /bin/bash "$DEPLOY_USER" || true
usermod -aG docker "$DEPLOY_USER"
usermod -aG sudo "$DEPLOY_USER"

# Firewall
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw --force enable

# fail2ban
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = 22
EOF
systemctl enable fail2ban
systemctl restart fail2ban

# App directory
mkdir -p "$APP_DIR"
chown "$DEPLOY_USER:$DEPLOY_USER" "$APP_DIR"

# Clone / pull repo
su - "$DEPLOY_USER" -c "git clone https://github.com/YOUR_ORG/clinic-system.git $APP_DIR || (cd $APP_DIR && git pull)"

# Generate secrets
cd "$APP_DIR"
bash scripts/generate-secrets.sh > .env.production
echo "APP_URL=https://${DOMAIN}" >> .env.production

# SSL cert
certbot certonly --nginx -d "$DOMAIN" --non-interactive --agree-tos --email admin@"${DOMAIN#*.}"

# Update nginx conf with domain
sed -i "s/clinic.yourdomain.com/$DOMAIN/g" docker/nginx/conf.d/clinic.conf

# Start stack
cp .env.production .env
docker compose up -d

# Wait and verify
sleep 10
docker compose ps
docker compose exec app php artisan migrate --force
docker compose exec app php artisan audit:verify-chain

# Backup cron
crontab -l 2>/dev/null | grep -v backup.sh > /tmp/crontab_new || true
echo "0 2 * * * /var/www/clinic-system/scripts/backup.sh >> /var/log/clinic-backup.log 2>&1" >> /tmp/crontab_new
crontab /tmp/crontab_new

# SSL auto-renew
echo "0 0,12 * * * root certbot renew --quiet --post-hook 'docker exec clinic-nginx nginx -s reload'" >> /etc/cron.d/certbot-renew

log "=== VPS setup complete. App running at https://${DOMAIN} ==="
