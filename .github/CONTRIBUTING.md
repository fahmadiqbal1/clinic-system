# Contributing

## Commit Signing

All commits to this repository should be **GPG or SSH signed** to verify authenticity.
This is especially important for a healthcare system handling PHI.

### Setup GPG signing:
```bash
gpg --full-generate-key
git config --global user.signingkey <your-key-id>
git config --global commit.gpgsign true
```

### Setup SSH signing:
```bash
git config --global gpg.format ssh
git config --global user.signingkey ~/.ssh/id_ed25519.pub
git config --global commit.gpgsign true
```

## Code of Conduct

Please be respectful and professional in all interactions. This project handles sensitive healthcare data — accuracy, security, and confidentiality are paramount.

## Submitting Changes

1. Fork the repository and create a feature branch.
2. Make your changes with signed commits.
3. Open a pull request describing what you changed and why.
4. Ensure all tests pass before requesting review.

## Reporting Security Issues

Do **not** open public issues for security vulnerabilities. See [SECURITY.md](../SECURITY.md) for the responsible disclosure process.
