<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Yaml\Yaml;

final class Version20261001140000 extends AbstractMigration
{
    /** Table => [unique index on code, prefixes of its translation keys]. */
    private const array VOCABULARIES = [
        'continent' => ['UNIQ_6CC70C7C77153098', ['continent.']],
        'launch' => ['UNIQ_79B757F577153098', ['launch.']],
        'material_type' => ['UNIQ_D8B63A1C77153098', ['material.']],
        'restraint' => ['UNIQ_6FCF42977153098', ['restraint.']],
        'seating_type' => ['UNIQ_8CED316377153098', []],
        'status' => ['UNIQ_7B00651C77153098', ['status.']],
        'tag' => ['UNIQ_389B78377153098', ['pro.', 'con.']],
    ];

    /** Rows typed in English in the admin that get a translation key. */
    private const array NAMED = [
        'launch' => ['Flywheel' => 'launch.flywheel', 'Conveyor belt' => 'launch.conveyor'],
        'material_type' => ['Steel' => 'material.steel', 'Wood' => 'material.wood', 'Hybrid' => 'material.hybrid'],
    ];

    /** Former country translation keys. */
    private const array KEYS = [
        'country.spain' => 'ES',
        'country.italy' => 'IT',
        'country.sweden' => 'SE',
        'country.france' => 'FR',
        'country.netherlands' => 'NL',
        'country.belgium' => 'BE',
        'country.austria' => 'AT',
        'country.finland' => 'FI',
        'country.norway' => 'NO',
        'country.denmark' => 'DK',
        'country.switzerland' => 'CH',
        'country.uk' => 'GB',
        'country.germany' => 'DE',
        'country.usa' => 'US',
        'country.japan' => 'JP',
        'country.canada' => 'CA',
        'country.taiwan' => 'TW',
        'country.southkorea' => 'KR',
        'country.australia' => 'AU',
        'country.russia' => 'RU',
        'country.colombia' => 'CO',
        'country.mexico' => 'MX',
        'country.guatemala' => 'GT',
        'country.ukraine' => 'UA',
        'country.china' => 'CN',
        'country.hungary' => 'HU',
        'country.israel' => 'IL',
        'country.malaysia' => 'MY',
        'country.portugal' => 'PT',
        'country.argentina' => 'AR',
        'country.brazil' => 'BR',
        'country.thailand' => 'TH',
        'country.poland' => 'PL',
        'country.vietnam' => 'VN',
        'country.turkey' => 'TR',
        'country.southafrica' => 'ZA',
        'country.singapore' => 'SG',
        'country.peru' => 'PE',
        'country.uae' => 'AE',
        'country.qatar' => 'QA',
        'country.india' => 'IN',
        'country.indonesia' => 'ID',
        'country.iraq' => 'IQ',
        'country.newzealand' => 'NZ',
        'country.lebanon' => 'LB',
        'country.burma' => 'MM',
        'country.cyprus' => 'CY',
        'country.ireland' => 'IE',
        'country.czech' => 'CZ',
        'country.mongolia' => 'MN',
    ];

    /** Names typed in the admin that differ from the CLDR English name. */
    private const array ALIASES = [
        'Bosnia and Herzegovina' => 'BA',
        'Democratic Republic of the Congo' => 'CD',
        'Ivory Coast' => 'CI',
        'Kuweit' => 'KW',
        'Lybia' => 'LY',
        'Palestine' => 'PS',
    ];

    public function getDescription(): string
    {
        return 'Vocabularies: code (translation key, ISO 3166-1 alpha-2 for countries) split from name, which becomes the English label.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE country ADD code VARCHAR(2) DEFAULT NULL');

        $update = 'UPDATE country SET code = ?, name = ? WHERE name = ?';
        $names = Countries::getNames('en');

        // Rows already holding the English name. Kosovo (XK) needs SYMFONY_INTL_WITH_USER_ASSIGNED.
        foreach ($names as $code => $name) {
            $this->addSql($update, [$code, $name, $name]);
        }

        foreach (self::KEYS + self::ALIASES as $former => $code) {
            $this->addSql($update, [$code, $names[$code], $former]);
        }

        // Fails on a country left without a code: add it to ALIASES.
        $this->addSql('ALTER TABLE country MODIFY code VARCHAR(2) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5373C96677153098 ON country (code)');

        // The key moves from name to code, name takes its English translation.
        $labels = Yaml::parseFile(\dirname(__DIR__).'/translations/database+intl-icu.en.yml');

        foreach (self::VOCABULARIES as $table => [$index, $prefixes]) {
            $this->addSql(\sprintf('ALTER TABLE %s ADD code VARCHAR(64) DEFAULT NULL', $table));

            foreach ($prefixes as $prefix) {
                $this->addSql(\sprintf('UPDATE %s SET code = name WHERE name LIKE ?', $table), [$prefix.'%']);
            }

            foreach (self::NAMED[$table] ?? [] as $name => $code) {
                $this->addSql(\sprintf('UPDATE %s SET code = ? WHERE name = ?', $table), [$code, $name]);
            }

            foreach ($labels as $code => $label) {
                if (array_any($prefixes, static fn (string $prefix): bool => str_starts_with($code, $prefix))) {
                    $this->addSql(\sprintf('UPDATE %s SET name = ? WHERE code = ?', $table), [$label, $code]);
                }
            }

            $this->addSql(\sprintf('CREATE UNIQUE INDEX %s ON %s (code)', $index, $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::KEYS as $key => $code) {
            $this->addSql('UPDATE country SET name = ? WHERE code = ?', [$key, $code]);
        }

        // Dropping the column drops its index.
        $this->addSql('ALTER TABLE country DROP COLUMN code');

        foreach (array_keys(self::VOCABULARIES) as $table) {
            foreach (self::NAMED[$table] ?? [] as $name => $code) {
                $this->addSql(\sprintf('UPDATE %s SET name = ?, code = NULL WHERE code = ?', $table), [$name, $code]);
            }

            $this->addSql(\sprintf('UPDATE %s SET name = code WHERE code IS NOT NULL', $table));
            $this->addSql(\sprintf('ALTER TABLE %s DROP COLUMN code', $table));
        }
    }
}
