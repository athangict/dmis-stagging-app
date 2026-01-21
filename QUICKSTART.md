# Quick Start Guide for DevOps Deployment

## 1. Initial Setup (One-time)

### Step 1: Create GitHub Repository
1. Go to https://github.com/new
2. Create a new repository (use `dmis-stagging-app` under the `athangict` account)
3. Don't initialize with README (we already have files)

### Step 2: Configure Git Remote
```powershell
cd c:\xampp\htdocs\staging.com
git remote add origin https://github.com/athangict/dmis-stagging-app.git
git branch -M main
```

### Step 3: Configure Kubernetes Access and GitHub Secrets

**Prerequisites for Private Clusters:**
- Self-hosted runner installed (see [DEPLOYMENT.md](DEPLOYMENT.md) for full setup)
- kubectl installed on runner machine
- RBAC permissions granted (see [RBAC-SETUP.md](RBAC-SETUP.md))

**Encode your kubeconfig:**
```powershell
# Read and encode kubeconfig
$kubeContent = Get-Content "path\to\kubeconfig.yaml" -Raw
$kubeBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($kubeContent))

# Copy to clipboard
Set-Clipboard -Value $kubeBase64

# Verify length (should be ~9000+ characters)
Write-Host "Base64 length: $($kubeBase64.Length) characters"
Write-Host "First 50 chars: $($kubeBase64.Substring(0,50))"
```

### Step 4: Add GitHub Secrets (Actions)
Go to **Settings → Secrets and variables → Actions**: https://github.com/athangict/dmis-stagging-app/settings/secrets/actions

Add this secret:
1. **KUBE_CONFIG_SECRET**: 
   - Value: Paste the base64 string from clipboard (Ctrl+V)
   - Should be ~9000+ characters
   - Should start with: `YXBpVmVyc2lvbjogdjEK...`
   - **Important**: No extra whitespace or newlines
   
**Note**: The kubeconfig user must have admin permissions in the `staging` namespace. If you get permission errors, see [RBAC-SETUP.md](RBAC-SETUP.md).

### Step 5: Update Configuration Files

#### Update k8s/deployment.yaml:
- Line 92: Replace `your-registry/staging-app:latest` with `ghcr.io/athangict/dmis-stagging-app:latest`
- Line 103: Replace `staging.yourdomain.com` with your actual domain
- Lines 28-31: Update database credentials

#### Update .env.example:
- Change database password
- Change APP_URL to your domain

### Step 6: Build and Test Docker Image Locally
```powershell
# Build image
docker build -t staging-app:test .

# Test run (optional)
docker run -p 8080:80 staging-app:test
# Visit http://localhost:8080 to verify
```

### Step 7: Initial Commit and Push
```powershell
git add .
git commit -m "Initial commit: Application with DevOps setup"
git push -u origin main
```

This will automatically trigger the GitHub Actions workflow!

### Step 8: Deploy to Kubernetes
The GitHub Actions workflow will:
1. ✅ Build Docker image
2. ✅ Push to GitHub Container Registry
3. ✅ Deploy to Kubernetes cluster
4. ✅ Verify deployment

Monitor the workflow at: https://github.com/athangict/dmis-stagging-app/actions

### Step 9: Verify Deployment
```powershell
# Check deployment status
kubectl get all -n staging

# Check pods are running
kubectl get pods -n staging

# View application logs
kubectl logs -f deployment/staging-app -n staging
```

### Step 10: Setup Database
```powershell
# Get MySQL pod name
kubectl get pods -n staging -l app=mysql

# Copy SQL file to pod
kubectl cp data/DB.sql staging/MYSQL_POD_NAME:/tmp/DB.sql

# Import database
kubectl exec -it MYSQL_POD_NAME -n staging -- mysql -u staging_user -p staging_db < /tmp/DB.sql
```

## 2. Daily Development Workflow

### Make changes to code
```powershell
# Edit files as needed
# ...

# Commit and push
git add .
git commit -m "Description of changes"
git push
```

### Automatic Deployment
- GitHub Actions automatically builds and deploys
- Monitor at: https://github.com/athangict/dmis-stagging-app/actions
- Application updates in ~5 minutes

### Check Deployment
```powershell
# View deployment history
kubectl rollout history deployment/staging-app -n staging

# Check current status
kubectl get deployment staging-app -n staging
```

## 3. Common Tasks

### View Logs
```powershell
# Real-time logs
kubectl logs -f deployment/staging-app -n staging

# Last 100 lines
kubectl logs --tail=100 deployment/staging-app -n staging

# MySQL logs
kubectl logs -f deployment/mysql -n staging
```

### Scale Application
```powershell
# Scale to 3 replicas
kubectl scale deployment staging-app --replicas=3 -n staging

# Verify
kubectl get pods -n staging
```

### Rollback Deployment
```powershell
# Rollback to previous version
kubectl rollout undo deployment/staging-app -n staging

# Rollback to specific revision
kubectl rollout undo deployment/staging-app --to-revision=2 -n staging
```

### Access Application Shell
```powershell
# Get pod name
kubectl get pods -n staging -l app=staging-app

# Access shell
kubectl exec -it POD_NAME -n staging -- /bin/bash
```

### Database Backup
```powershell
# Create backup
kubectl exec -it deployment/mysql -n staging -- mysqldump -u staging_user -p staging_db > backup-$(Get-Date -Format 'yyyy-MM-dd').sql
```

## 4. Troubleshooting

### Pods not starting?
```powershell
kubectl describe pod POD_NAME -n staging
kubectl logs POD_NAME -n staging
```

### Database connection failed?
```powershell
# Test connectivity
kubectl exec -it deployment/staging-app -n staging -- ping mysql-service

# Check MySQL is running
kubectl get pods -n staging -l app=mysql
```

### Image pull errors?
- Check GitHub Container Registry permissions
- Verify image name in deployment.yaml
- Check GitHub Actions build logs

### Ingress not working?
```powershell
# Check ingress status
kubectl get ingress -n staging
kubectl describe ingress staging-ingress -n staging

# Ensure ingress controller is installed
kubectl get pods -n ingress-nginx
```

### Git push fails: "Repository not found"
1. Confirm the repo exists: https://github.com/athangict/dmis-stagging-app
2. Point the remote to the exact repo URL (no trailing slash):
	```powershell
	git remote set-url origin https://github.com/athangict/dmis-stagging-app.git
	git remote -v
	```
3. Re-run the push (ensure the branch is `main`):
	```powershell
	git push -u origin main
	```
4. If still blocked, authenticate with GitHub (PAT with `repo` scope or `gh auth login`):
	```powershell
	gh auth login
	# or push once and enter PAT when prompted
	```
5. Org/private repos: confirm your GitHub user has write access to the repo.

## 5. Environment Management

### Development (Local)
- Use XAMPP/local server
- Database: localhost
- No containerization needed

### Staging (Kubernetes)
- Namespace: `staging`
- Auto-deployment on push to `main` branch
- Domain: staging.yourdomain.com

### Production (Kubernetes)
Create separate namespace and workflow:
```powershell
# Create production namespace
kubectl create namespace production

# Deploy to production (manual)
kubectl apply -f k8s/production-deployment.yaml
```

## 6. Monitoring Setup (Optional)

### Install Prometheus & Grafana
```powershell
# Add Helm repo
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update

# Install
helm install monitoring prometheus-community/kube-prometheus-stack -n monitoring --create-namespace
```

### Access Grafana
```powershell
kubectl port-forward -n monitoring svc/monitoring-grafana 3000:80
# Visit http://localhost:3000
# Default: admin/prom-operator
```

## Support

For issues:
1. Check GitHub Actions logs
2. Check Kubernetes pod logs
3. Verify all secrets are configured
4. Ensure cluster has enough resources

## Next Steps

- [ ] Set up automated database backups
- [ ] Configure SSL certificates (Let's Encrypt)
- [ ] Set up monitoring and alerts
- [ ] Create staging/production environments
- [ ] Implement auto-scaling (HPA)
- [ ] Set up log aggregation
