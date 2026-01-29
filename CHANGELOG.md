<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
<!--- END HEADER -->

## [0.1.0] - 2026-01-28

Initial release of kachnitel/auditor-bundle, a fork of damienharper/auditor-bundle with additional features.

### Features

* **AuditContext**: Request-scoped context service for passing metadata (notes, reasons) to audits
* **AuditReader**: Query interface for audit entries with filtering by entity, IDs, date ranges
* **Snapshot**: Reconstructs entity state at any point in history by reversing diffs
* **EventAuditService**: Creates EVENT-type audits for domain events (vs field-change audits)
* **Admin Integration**: DataSource/Factory for admin-bundle with filtering and pagination
* **Timeline View**: Filter and view audits by user with timeline visualization
* **Request ID Tracking**: Correlate audits from the same HTTP request
* **System Events Toggle**: Filter system vs user events in admin UI
* **Context Display**: Show audit context metadata in admin UI
* **Twig Components**: Modern component-based templates

### Changed

* Namespace changed from `DH\AuditorBundle` to `Kachnitel\AuditorBundle`
