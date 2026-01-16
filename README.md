# kachnitel/auditor-bundle

Fork of [DamienHarper/auditor-bundle](https://github.com/DamienHarper/auditor-bundle) with additional features.

Integrates `auditor` library into Symfony 6.4+ applications for Doctrine ORM audit logging.

## Installation

```bash
composer require kachnitel/auditor-bundle
```

## Fork Features

This fork adds:
- **AuditContext**: Add metadata (notes, reasons) to audit entries
- **AuditReader**: Query audit entries with filters
- **Snapshot**: Reconstruct entity state at any point in history
- **EventAuditService**: Create EVENT-type audits for domain events
- **Admin Integration**: Auto-registered audit data sources with `kachnitel/admin-bundle`

**📖 [See FORK.md for detailed documentation and usage examples](FORK.md)**


**📦 Migrating from damienharper/auditor-bundle? [See MIGRATION.md](MIGRATION.md)**


<details>
<summary><strong>View feature details</strong></summary>

### Core Services
- **AuditContext**: Request-scoped service for adding metadata (notes, reasons) to audits via `@context` injection
- **AuditReader**: Query interface for retrieving audit entries with filters (entity type, IDs, date ranges, operations)
- **Snapshot**: Reconstructs entity state at any point in history by reversing audit diffs
- **EventAuditService**: Creates EVENT-type audits for domain events

### Admin Bundle Integration
- Preview entity modifications in admin list views
- Auto-registered when `kachna/admin-bundle` is installed
- Browse audit logs with filtering and pagination

### Code Quality
- PHPStan level `max` with strict type coverage
- Docker-based multi-version testing: `make tests php=8.3 sf=7.1`

</details>


## Documentation
Original `auditor-bundle` documentation: [https://damienharper.github.io/auditor-docs/](https://damienharper.github.io/auditor-docs/docs/auditor-bundle/index.html)


## Requirements

- PHP >= 8.2
- Symfony >= 6.4
- Doctrine ORM >= 3.1


## Usage
Once [installed](https://damienharper.github.io/auditor-docs/docs/auditor-bundle/installation.html) and [configured](https://damienharper.github.io/auditor-docs/docs/auditor-bundle/configuration/general.html), any database change
affecting audited entities will be logged to audit logs automatically.
Also, running schema update or similar will automatically setup audit logs for every
new auditable entity.


## Contributing

<details>
<summary>Contribution guidelines</summary>

`auditor-bundle` is an open source project. Contributions made by the community are welcome.
Send me your ideas, code reviews, pull requests and feature requests to help us improve this project.

Do not forget to provide unit tests when contributing to this project.
To do so, follow instructions in this dedicated [README](tests/README.md)

</details>


## Credits
- Original bundle by [Damien Harper](https://github.com/DamienHarper)
- Thanks to [all contributors](https://github.com/DamienHarper/auditor-bundle/graphs/contributors) to the original project


## License
`auditor-bundle` is free to use and is licensed under the [MIT license](http://www.opensource.org/licenses/mit-license.php)
