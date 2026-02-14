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
- **Per-Domain Setup**: When deploying to a new domain, update `auth/config.example.php` with the correct `base_url` before committing. This ensures the auto-copied config is domain-aware.
- For Laravel or Node apps, you can add build commands to the tasks list.

## Multi-Domain Deployment

To deploy to multiple domains using the same repo:

**Before deployment to a new domain:**

1. Update your local `auth/config.example.php`:
   ```php
   'base_url' => 'https://your-new-domain.com',
   ```

2. Commit and push:
   ```bash
   git add auth/config.example.php
   git commit -m "Update base_url for new domain"
   git push
   ```

3. In that domain's cPanel, update `.cpanel.yml` with the new path:
   ```yaml
   - export DEPLOYPATH=/home/hp6xh47j1ds2/public_html/your-new-domain.com
   ```

4. Push and cPanel will deploy automatically with the correct domain in `config.php`.
