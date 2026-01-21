# Kubernetes RBAC Setup for GitHub Actions Deployment

This document describes the required Kubernetes permissions for automated deployments via GitHub Actions.

## Required Permissions

The kubeconfig user (e.g., `singye`) needs permissions to manage resources in the `staging` namespace.

## Setup Instructions

These commands should be run by a Kubernetes cluster administrator with cluster-admin privileges.

### Option 1: Namespace Admin (Recommended)

Grant full admin access to the `staging` namespace only:

```bash
# Create the staging namespace (if it doesn't exist)
kubectl create namespace staging

# Grant admin role for the staging namespace
kubectl create rolebinding singye-staging-admin \
  --clusterrole=admin \
  --user=singye \
  --namespace=staging
```

**Permissions granted:**
- Create/update/delete deployments, pods, services
- Manage secrets, configmaps
- View logs and events
- **Limited to staging namespace only** ✓ (Secure)

### Option 2: Cluster Admin (Not Recommended)

Grant cluster-wide admin access (use only if managing multiple namespaces):

```bash
kubectl create clusterrolebinding singye-cluster-admin \
  --clusterrole=cluster-admin \
  --user=singye
```

**Permissions granted:**
- Full access to all namespaces
- Can create/delete namespaces
- Access to cluster-level resources
- **Unrestricted access** ⚠️ (Less secure)

## Verify Permissions

After granting permissions, verify the user can access the staging namespace:

```bash
# Check if user can list deployments in staging
kubectl auth can-i list deployments -n staging --as=singye

# Check if user can update deployments in staging
kubectl auth can-i update deployments -n staging --as=singye

# Check if user can create pods in staging
kubectl auth can-i create pods -n staging --as=singye
```

All commands should return `yes`.

## What the Deployment Workflow Needs

The GitHub Actions workflow performs these operations:

1. **Configure kubectl** with the kubeconfig secret
2. **Verify connection** to the cluster
3. **Update deployment image** to the new version
4. **Check rollout status** to ensure successful deployment
5. **List pods** to verify they're running

All these operations require the following permissions in the `staging` namespace:
- `deployments.apps`: get, list, patch, update
- `pods`: get, list, watch
- `replicasets.apps`: get, list, watch

The `admin` ClusterRole includes all these permissions.

## Troubleshooting

### Error: "User 'singye' cannot list resource 'deployments'"

**Problem**: The user doesn't have RBAC permissions yet.

**Solution**: Run the namespace admin command above.

### Error: "namespaces 'staging' not found"

**Problem**: The staging namespace doesn't exist.

**Solution**: Create it first:
```bash
kubectl create namespace staging
```

### Verify Current Permissions

Check what the user can currently do:

```bash
# List all permissions for user in staging namespace
kubectl auth can-i --list --namespace=staging --as=singye

# Check specific permission
kubectl auth can-i get deployments -n staging --as=singye
```

## Security Best Practices

1. ✅ **Use namespace-specific RoleBinding** instead of cluster-wide ClusterRoleBinding
2. ✅ **Limit to staging namespace** - production should use a different user/namespace
3. ✅ **Rotate kubeconfig credentials** periodically
4. ✅ **Store kubeconfig as GitHub secret** with restricted access
5. ✅ **Use self-hosted runner** in secure network for private clusters
6. ⚠️ **Avoid storing admin kubeconfig** in version control or public locations

## Next Steps

After RBAC permissions are granted:

1. Verify permissions using the commands above
2. Update the `KUBE_CONFIG_SECRET` in GitHub Actions secrets
3. Re-run the deployment workflow
4. Monitor the self-hosted runner logs for successful deployment

For complete deployment setup, see [DEPLOYMENT.md](DEPLOYMENT.md).
