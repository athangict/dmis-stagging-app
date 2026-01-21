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

- **KUBE_CONFIG**: Your Kubernetes config file (base64 encoded)
  ```bash
  # On Linux/Mac:
  cat ~/.kube/config | base64
  
  # On Windows PowerShell:
  [Convert]::ToBase64String([System.IO.File]::ReadAllBytes("$env:USERPROFILE\.kube\config"))
  ```

### 3. Update Kubernetes Manifests

Edit `k8s/deployment.yaml`:

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
