# Migration from damienharper/auditor-bundle

This is an independent fork with a different namespace. Migrating requires code changes.

## 1. Update composer.json

```diff
- "damienharper/auditor-bundle": "^6.0"
+ "kachnitel/auditor-bundle": "^6.0"
```

## 2. Update bundle registration

```diff
// config/bundles.php
- DH\AuditorBundle\DHAuditorBundle::class => ['all' => true],
+ Kachnitel\AuditorBundle\KachnitelAuditorBundle::class => ['all' => true],
```

## 3. Update configuration file

Rename `config/packages/dh_auditor.yaml` to `config/packages/kachnitel_auditor.yaml`:

```diff
- dh_auditor:
+ kachnitel_auditor:
      enabled: true
-     user_provider: 'dh_auditor.user_provider'
-     security_provider: 'dh_auditor.security_provider'
-     role_checker: 'dh_auditor.role_checker'
+     user_provider: 'kachnitel_auditor.user_provider'
+     security_provider: 'kachnitel_auditor.security_provider'
+     role_checker: 'kachnitel_auditor.role_checker'
      # ... rest of config unchanged
```

## 4. Update use statements

```diff
- use DH\AuditorBundle\Service\AuditContext;
- use DH\AuditorBundle\Service\AuditReader;
+ use Kachnitel\AuditorBundle\Service\AuditContext;
+ use Kachnitel\AuditorBundle\Service\AuditReader;
```

## 5. Update service references

If you reference services by ID:
```diff
- $container->get('dh_auditor.context');
+ $container->get('kachnitel_auditor.context');
```

## Quick reference

| Old | New |
|-----|-----|
| `DH\AuditorBundle\` | `Kachnitel\AuditorBundle\` |
| `dh_auditor:` (config) | `kachnitel_auditor:` |
| `dh_auditor.*` (services) | `kachnitel_auditor.*` |
