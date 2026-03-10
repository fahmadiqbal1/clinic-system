# Security Policy

## Reporting a Vulnerability

This repository manages a healthcare clinic system and may process Protected Health Information (PHI).

If you discover a security vulnerability, **do not open a public issue**. Instead, email the maintainer directly or use GitHub's private vulnerability reporting feature.

**Please include:**
- Description of the vulnerability
- Steps to reproduce
- Potential impact

We aim to respond within 72 hours.

## Sensitive Data Warning

This system integrates with patient records, prescriptions, and AI analysis. All production deployments must:
- Use a properly secured `.env` file (never committed to version control)
- Enable PHI encryption on all sensitive models
- Rotate any credentials that were ever committed to the repository's git history
- Use HTTPS in production at all times
