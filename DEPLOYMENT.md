# Auto-Deploy With .cpanel.yml (Option 2)

This method lets you push to GitHub/GitLab/Bitbucket and have cPanel automatically deploy changes to your subdomain document root.

## 1. In cPanel, create or clone your Git repo

1. Log in to cPanel.
2. Go to Files -> Git Version Control.
3. Click Create (or select an existing repo).
4. Choose:
  - Clone URL: your GitHub/GitLab repo
  - Repository Path: for example /home/hp6xh47j1ds2/repositories/workout
  - Note: do NOT put this inside public_html

Now cPanel has its own local copy of your repo.

## 2. Create .cpanel.yml at your repo root

Add a file named .cpanel.yml at the root of the repo (locally).

## 3. Add deployment instructions to .cpanel.yml

Use this example (already set to your cPanel username and subdomain path):

```yaml
deployment:
  tasks:
    - export DEPLOYPATH=/home/hp6xh47j1ds2/public_html/fitness.eclipsewatertechnologies.com
    - /bin/cp -r * $DEPLOYPATH
```

This script will:
1. Set a deployment path.
2. Copy all files from your repo to the subdomain document root.

If you want to clear the destination before copying:

```yaml
deployment:
  tasks:
    - export DEPLOYPATH=/home/hp6xh47j1ds2/public_html/fitness.eclipsewatertechnologies.com
    - /bin/rm -rf $DEPLOYPATH/*
    - /bin/cp -r * $DEPLOYPATH
```

## 4. Commit and push the .cpanel.yml file

```bash
git add .cpanel.yml
git commit -m "Add cPanel deploy script"
git push
```

## 5. cPanel receives it and deploys automatically

After pushing, cPanel will:
- Fetch changes
- Run your .cpanel.yml commands
- Copy updated files into the subdomain document root

Deployment logs are available in:
Git Version Control -> (Your Repo) -> Manage -> Pull or Deployment Logs

## Notes

- This repository is a PHP app, so no build step is required.
- `.cpanel.yml` must be at the repo root (not inside a subfolder).
- **Auto-Configuration**: The deployment script automatically detects your domain and updates `auth/config.php` with the correct `base_url`. This means deployments to *any* domain will work without manual config changes.
- For Laravel or Node apps, you can add build commands to the tasks list.

## Auto-Deploy Process

When you deploy to a new domain:

1. `.cpanel.yml` copies all files to the subdomain document root.
2. If `config.php` is missing, it's created from `config.example.php`.
3. **`auth/setup-deployment.php` runs automatically**:
   - Detects your current domain and protocol (HTTP/HTTPS).
   - Updates `auth/config.php` base_url to match.
   - Works for any domain without manual intervention.

This means you can deploy to multiple domains and each will auto-configure correctly.
