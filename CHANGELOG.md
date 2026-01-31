<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
<!--- END HEADER -->

## [0.2.0](https://github.com/kachnitel/auditor-bundle/compare/0.1.0...0.2.0) (2026-01-31)

### Bug Fixes

* Enhance type filter to handle JSON string format and add tests for JSON input ([4d9742](https://github.com/kachnitel/auditor-bundle/commit/4d97426a963d046d80fcd46ec3a91ef62bab1813))
* Update php-conventional-changelog dependency and add fork reference ([b1574d](https://github.com/kachnitel/auditor-bundle/commit/b1574d1970d52d8e1f04cc67863ed80e23dba2c5))


---

## [0.1.0](https://github.com/kachnitel/auditor-bundle/compare/74102b49ae5b8fb2a7acb027f0032a4b22e058b5...0.1.0) (2026-01-28)

### Features

* Add abstract EventSubscriber to handle system events ([955ccb](https://github.com/kachnitel/auditor-bundle/commit/955ccb0d154e3be23f4db19578b1ec26b5187ccd))
* Add AuditContext, Snapshot, and EventAuditService ([5699b4](https://github.com/kachnitel/auditor-bundle/commit/5699b4608020388a4fb7bc06c160f1c85ed2a233))
* Add request ID tracking for correlating audits from same HTTP request ([647e98](https://github.com/kachnitel/auditor-bundle/commit/647e98a27c0d331a5520a5200cf0f1edfd8f76dc))
* Add user audit "timeline" filter ([806258](https://github.com/kachnitel/auditor-bundle/commit/806258979062a38f37bd203c869b0ef77244847d))
* Move to kachnitel/auditor-bundle package ([351346](https://github.com/kachnitel/auditor-bundle/commit/351346337a8a331f1b5a8afb83718e2ac088fc87))
* Preview entity changes in admin list view ([5c4c97](https://github.com/kachnitel/auditor-bundle/commit/5c4c97c991cb1271158463ff4f56c6d3d798c8dc))
* Show context in admin ui ([50620a](https://github.com/kachnitel/auditor-bundle/commit/50620a9addb7b01a099a9f69fc405c48ab5d708b))
* System events toggle in main list view ([a3599b](https://github.com/kachnitel/auditor-bundle/commit/a3599b7bb66b6d0e9c917c8cb6da31fada0c5718))
* Timeline view for relevant events ([a8aca3](https://github.com/kachnitel/auditor-bundle/commit/a8aca399ee7bb5bd344dd6a45a4081c5ba080965))
* Use (optional) admin-bundle for vieweing with filters ([130ec1](https://github.com/kachnitel/auditor-bundle/commit/130ec1dc30827d9326f3fcf6692a8df04f46949d))

### Bug Fixes

* `ViewerController` (#379) ([7c226b](https://github.com/kachnitel/auditor-bundle/commit/7c226bbf2cf1485266fcb737589db72dd755fd56))
* Add AuditReader service and fix Snapshot ([a9e1b8](https://github.com/kachnitel/auditor-bundle/commit/a9e1b8e97668726bcd6bdb85f629798161e4d79f))
* Correct doc block for AuditReader event support ([9be665](https://github.com/kachnitel/auditor-bundle/commit/9be6659e13945615e96fe41d012ce35cd7b05ffd))
* EnumClass method call in updated admin-bundle (0.3.1+) ([2ce24e](https://github.com/kachnitel/auditor-bundle/commit/2ce24e1e8b85a5e6d688025c22cbd1c581adcd7e))
* Fix test stub for FilterMetadata ([8e511a](https://github.com/kachnitel/auditor-bundle/commit/8e511acaa4ad5723ad251771ea4d93428d8582ad))
* Missed namespace change in twig templates ([10f9db](https://github.com/kachnitel/auditor-bundle/commit/10f9dbc3ea6161771d43a898f1d7c436b4eec486))
* Prevent an error showing _changes-preview with missing old/new value ([5f4a6e](https://github.com/kachnitel/auditor-bundle/commit/5f4a6e82e4239a4da6ff141afffc71155688f8d2))
* Update enumClass in FilterMetadata test stub ([600f60](https://github.com/kachnitel/auditor-bundle/commit/600f603c7f26735424269c1986be0f45dbd63045))
* WIP on timeline change previews ([833092](https://github.com/kachnitel/auditor-bundle/commit/833092d63e581387d7e9bcbdc63ce9726bd29599))

### Code Refactoring

* Use twig components instead of partials ([1ee0a5](https://github.com/kachnitel/auditor-bundle/commit/1ee0a5c9a796857ae09f9dbb7207046461a00c28))

### Documentation

* Document fork's features ([3b988c](https://github.com/kachnitel/auditor-bundle/commit/3b988cf7c9fac125f9d7025b1ecd2fb6e2cda4b6))
* Update readme and fork docs ([3fef06](https://github.com/kachnitel/auditor-bundle/commit/3fef0659692a9b996271ae840537f24ad504efa1))


---

