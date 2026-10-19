<h1 align="center">
  Flagbit Table Attribute for Akeneo PIM
  <br>
</h1>

<h4 align="center">Adds the new attribute type Table for Akeneo products.</h4>

<p align="center">
    <a href="https://github.com/flagbit/akeneo-table-attribute-bundle/actions">
        <img src="https://github.com/flagbit/akeneo-table-attribute-bundle/actions/workflows/main.yml/badge.svg"/>
    </a>
    <img src="https://poser.pugx.org/flagbit/table-attribute-bundle/downloads?format=flat-square">
    <a href="https://scrutinizer-ci.com/g/Flagbit/akeneo-table-attribute-bundle">
        <img src="https://img.shields.io/scrutinizer/g/flagbit/akeneo-table-attribute-bundle.svg?style=flat-square">
    </a>
    <a href="https://packagist.org/packages/flagbit/table-attribute-bundle">
        <img src="https://img.shields.io/packagist/v/flagbit/table-attribute-bundle.svg?style=flat-square">
    </a>
    <a href="LICENSE">
        <img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square">
    </a>
</p>

<p align="center">
  <a href="#key-features">Key Features</a> •
  <a href="#installation">Installation</a> •
  <a href="#compatibility">Compatibility</a> •
  <a href="#development">Development</a> •
  <a href="#contributing">Contributing</a>
</p>

## Key Features

Provides a _table_ as attribute type where you can define a set of columns of different types and validation rules.

#### Column Types

* Text
* Number (Integer or Decimal)
* Simple select
* Simple select from URL
* Simple select from attribute

A _Simple select from attribute_ column offers the options of a simple select attribute of the PIM, chosen in the
settings of the column, instead of options of its own. Its cells store the option codes. They are not validated against
the options of the attribute: imports and the API store any code, and a code that is not an option of the attribute
(for example of an option deleted later) stays stored and shows as _INVALID CODE_ in the product edit form, with the
code as tooltip. Saving the product keeps such a code until another option is chosen for the cell.

#### Changing the columns

Columns can be added to and deleted from a table attribute that products already have values for. Whenever products
are read (API, exports, product edit form, and the products loaded by imports, mass edits and other saves), the rows of
their table values are brought to the current columns, in the order of the columns: the keys of deleted columns are
removed and the columns added since the row was saved are set to `null`. A value whose rows already have the columns is
returned as it is stored.

The database keeps the rows as they were saved until the product is saved again, for any reason, which stores them with
the current columns. Until then, adding a column back with the code of a deleted one brings its old cells back.
Values sent to the PIM are still checked against the columns: a key of a deleted column is rejected.

#### Import/Export

The extension supports the standard Akeneo product import/export, so you don't need to create any special import/export
profile for table information.

All product information related to attributes of type _table_ will be imported/exported as JSON.

## Installation

Add Mijora repository to composer.json:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/mijora/akeneo-table-attribute-bundle"
    }
]
```

Simply install the package from custom branch with the following command:

``` bash
composer require flagbit/table-attribute-bundle:dev-pim-2026-support
```

### Enable the bundle

Enable the bundle in the kernel:

``` php
<?php
// config/bundles.php

return [
    // ...
    Flagbit\Bundle\TableAttributeBundle\FlagbitTableAttributeBundle::class => ['all' => true],
];
```

#### Configuration

Add `mapping_overrides` in a new `config/packages/table.yml` file or an existing one:

``` yml
akeneo_storage_utils:
    mapping_overrides:
        -
            original: Akeneo\Pim\Structure\Component\Model\AttributeOption
            override: Flagbit\Bundle\TableAttributeBundle\Entity\AttributeOption
```

#### Import the routing

Now that you have activated and configured the bundle, you need to import the routing files.

``` yml
# config/routes/flagbit_table_attribute.yml
flagbit_table_attribute:
    resource: "@FlagbitTableAttributeBundle/Resources/config/routing.yml"
```

Clear the cache:

``` bash
php bin/console --env=prod cache:clear
```


Do not use `doctrine:schema:update`: its output also drops important columns of the PIM. Add the columns of the bundle by hand, before the cache is cleared with the bundle enabled:

``` sql
ALTER TABLE pim_catalog_attribute_option ADD type VARCHAR(255) DEFAULT NULL, ADD type_config JSON DEFAULT NULL, ADD constraints JSON DEFAULT NULL;
```

The `type_config` and `constraints` columns are mapped as `json`, not as the `json_array` of the PIM 7 branch: that type is deprecated in doctrine/dbal 2 and gone in 3. Both types store the same JSON. If the columns are carried over from a PIM 7 install, drop their `(DC2Type:json_array)` comment:

``` sql
ALTER TABLE pim_catalog_attribute_option MODIFY type_config JSON DEFAULT NULL, MODIFY constraints JSON DEFAULT NULL;
```

Build and install the new front-end dependencies (new icon, etc.)

``` bash
make cache assets css javascript-prod javascript-extensions
```

If no docker

``` bash
NO_DOCKER=true make cache assets css javascript-prod javascript-extensions
```

## Compatibility

This extension supports the latest Akeneo PIM CE/EE stable versions:

* 2026.3 (branch `pim-2026-support`)
* 7.0 (branch `pim-7-support`)
* 6.0
* 5.0
* 4.0
* 3.2 (LTS)
* 3.0 (LTS)
* 2.3 (LTS)

## Development

### Running Test-Suits

The TableAttributeBundle is covered with tests and every change and addition has also to be covered with
unit or/and integration tests. It uses two testing suits: [PHPSpec](https://www.phpspec.net) and
[PHPUnit](https://phpunit.de/).

To run the tests you have to change to this project's root directory and run the following commands in your console:

``` bash
vendor/bin/phpunit
vendor/bin/phpspec run
```

### Coding style

TableAttributeBundle uses the [PSR-2](https://www.php-fig.org/psr/psr-2/) coding style and can be checked with
[Codesniffer](https://github.com/squizlabs/PHP_CodeSniffer).

``` bash
vendor/bin/phpcs --standard=PSR2 --extensions=php ./src
```

## Contributing

Contributions are always welcome! Please have a look at the [contribution guidelines](CONTRIBUTING.md) first.

## License

The TableAttributeBundle is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

#

<p align="center">
Supported with ❤ by <a href="https://www.flagbit.de">Flagbit GmbH & Co. KG</a>
</p>
