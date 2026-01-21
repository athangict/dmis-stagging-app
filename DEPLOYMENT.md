# Deployment Guide - GitOps/DevOps Setup

## Prerequisites

1. **Git Repository** (GitHub/GitLab/Bitbucket)
2. **Kubernetes Cluster** Access
3. **Container Registry** (GitHub Container Registry, Docker Hub, or private registry)

## Setup Steps

### 1. Initialize Git Repository

```bash
cd c:\xampp\htdocs\staging.com
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/your-username/staging-app.git
git push -u origin main
```

### 2. Configure GitHub Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions → New repository secret

Add the following secrets:

- **KUBE_CONFIG_SECRET**: Your Kubernetes config file (base64 encoded)
  
  **To set this correctly:**
  
  1. Generate the base64-encoded kubeconfig:
  ```powershell
  # On Windows PowerShell:
  $kubeContent = Get-Content "$env:USERPROFILE\.kube\config" -Raw
  $kubeBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($kubeContent))
  Write-Output $kubeBase64  # Copy this entire output
  ```
  
  2. Go to your GitHub repository → **Settings** → **Secrets and variables** → **Actions**
  3. Click **New repository secret**
  4. Name: `KUBE_CONFIG_SECRET`
  5. Value: Paste the entire base64 string (from step 1)
  6. Click **Add secret**
  
  **Verify the secret is set correctly:**
  - The encoded value should be a very long string (typically 2000+ characters)
  - Ensure no extra whitespace or line breaks are included
  - The decoded kubeconfig should point to your actual cluster endpoint (not localhost)

  #### Self-hosted runner (Windows) — required for private clusters

  If your Kubernetes API server is on a private IP (e.g., `https://172.30.x.x:6443`), use a self-hosted runner inside your network so GitHub Actions can reach it.

**Step 1: Download and configure the runner**

1. Download the Windows x64 GitHub Actions runner from Settings → Actions → Runners → New self-hosted runner
2. Extract to `C:\actions-runner`
3. Open PowerShell as Administrator and configure:
   ```powershell
   cd C:\actions-runner
   .\config.cmd --url https://github.com/athangict/dmis-stagging-app --token <RUNNER_TOKEN>
   ```
4. When prompted:
   - Runner group: Press Enter (Default)
   - Runner name: Press Enter or type "staging-runner"
   - Labels: Type "staging" or press Enter
   - Work folder: Press Enter (_work)
   - Run as service: Type "N" for now

**Step 2: Install kubectl**

The workflow requires kubectl to be available on the runner machine:

```powershell
# Create kubectl directory
New-Item -Path "C:\kubectl" -ItemType Directory -Force

# Download kubectl v1.29.0
Invoke-WebRequest -Uri "https://dl.k8s.io/release/v1.29.0/bin/windows/amd64/kubectl.exe" -OutFile "C:\kubectl\kubectl.exe"

# Verify installation
C:\kubectl\kubectl.exe version --client
```

**Step 3: Set PowerShell execution policy**

Allow the runner to execute PowerShell scripts:

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
```

**Step 4: Start the runner**

```powershell
cd C:\actions-runner
.\run.cmd
# Keep this window open; it should show "Listening for Jobs" then "Running job: deploy"
```

**Step 5: Grant Kubernetes RBAC permissions**

The kubeconfig user needs permissions to manage the staging namespace. Ask your cluster administrator to run:

```powershell
# Create staging namespace (if it doesn't exist)
kubectl create namespace staging

# Grant admin permissions for staging namespace
kubectl create rolebinding singye-staging-admin --clusterrole=admin --user=singye --namespace=staging
```

This grants full access to the `staging` namespace only (recommended for security).

**Step 6: Update kubeconfig secret**

After receiving the kubeconfig file from your cluster admin:

1. Encode it to base64:
   ```powershell
   $kubeContent = Get-Content "path\to\kubeconfig.yaml" -Raw
   $kubeBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($kubeContent))
   Set-Clipboard -Value $kubeBase64
   Write-Host "Base64 length: $($kubeBase64.Length) characters - copied to clipboard"
   ```

2. Update the GitHub secret:
   - Go to: https://github.com/athangict/dmis-stagging-app/settings/secrets/actions
   - Click on `KUBE_CONFIG_SECRET`
   - Paste the base64 value (Ctrl+V)
   - Click **Update secret**

3. Verify the secret (should start with base64 for "apiVersion"):
   ```
   First chars should be: YXBpVmVyc2lvbjogdjEK...
   Length should be: ~9000+ characters
   ```

**Troubleshooting:**

- **Runner not picking jobs**: Check that workflow uses `runs-on: self-hosted`
- **kubectl not found**: Ensure `C:\kubectl` exists and kubectl.exe is in that folder
- **Permission denied**: Verify RBAC permissions with `kubectl auth can-i list deployments -n staging`
- **Secret errors**: Ensure no whitespace/newlines in base64 secret; length should be 9000+ chars
- **Cluster unreachable**: Confirm runner machine can access the Kubernetes API endpoint on the private network

1. Replace `your-registry/staging-app:latest` with your actual registry path
2. Replace `staging.yourdomain.com` with your actual domain
3. Update database credentials in the Secret

### 4. Deploy to Kubernetes

#### First-time deployment:

```bash
# Create namespace and deploy
kubectl apply -f k8s/deployment.yaml

# Check status
kubectl get all -n staging
kubectl get pods -n staging
```

#### Configure database:

```bash
# Get MySQL pod name
kubectl get pods -n staging | grep mysql

# Import database
kubectl exec -it <mysql-pod-name> -n staging -- mysql -u staging_user -p staging_db < data/DB.sql
```

### 5. Continuous Deployment Workflow

Once set up, the workflow is:

1. **Developer pushes code** to main branch
2. **GitHub Actions automatically**:
   - Builds Docker image
   - Pushes to container registry
   - Updates Kubernetes deployment
   - Verifies deployment success

### 6. Alternative CI/CD Platforms

#### For GitLab CI (.gitlab-ci.yml):

```yaml
stages:
  - build
  - deploy

build:
  stage: build
  image: docker:latest
  services:
    - docker:dind
  script:
    - docker build -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA .
    - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA

deploy:
  stage: deploy
  image: bitnami/kubectl:latest
  script:
    - kubectl set image deployment/staging-app app=$CI_REGISTRY_IMAGE:$CI_COMMIT_SHA -n staging
    - kubectl rollout status deployment/staging-app -n staging
```

#### For Azure DevOps (azure-pipelines.yml):

```yaml
trigger:
  - main

pool:
  vmImage: 'ubuntu-latest'

stages:
- stage: Build
  jobs:
  - job: BuildAndPush
    steps:
    - task: Docker@2
      inputs:
        command: buildAndPush
        repository: staging-app
        tags: $(Build.BuildId)

- stage: Deploy
  jobs:
  - job: DeployToK8s
    steps:
    - task: Kubernetes@1
      inputs:
        command: set
        arguments: image deployment/staging-app app=staging-app:$(Build.BuildId) -n staging
```

## Monitoring & Maintenance

### View application logs:
```bash
kubectl logs -f deployment/staging-app -n staging
```

### Check deployment status:
```bash
kubectl get deployments -n staging
kubectl describe deployment staging-app -n staging
```

### Scale application:
```bash
kubectl scale deployment staging-app --replicas=3 -n staging
```

### Roll back deployment:
```bash
kubectl rollout undo deployment/staging-app -n staging
```

## Environment Configuration

Create environment-specific config files:

- `config/autoload/local.php` - Local development
- `config/autoload/production.php` - Production (use ConfigMap/Secrets in K8s)

## Security Best Practices

1. Never commit sensitive data (passwords, API keys)
2. Use Kubernetes Secrets for credentials
3. Enable RBAC in your cluster
4. Use network policies to restrict pod communication
5. Regularly update container images
6. Scan images for vulnerabilities

## Troubleshooting

### Pod not starting:
```bash
kubectl describe pod <pod-name> -n staging
kubectl logs <pod-name> -n staging
```

### Database connection issues:
```bash
kubectl exec -it deployment/staging-app -n staging -- ping mysql-service
```

### Image pull errors:
- Verify registry credentials
- Check image tag exists
- Ensure imagePullSecrets are configured

## Next Steps

1. Set up monitoring (Prometheus/Grafana)
2. Configure log aggregation (ELK/Loki)
3. Implement automated backups
4. Set up staging environment
5. Configure auto-scaling (HPA)
