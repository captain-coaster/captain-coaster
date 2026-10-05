<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\Launch;
use App\Service\VocabularyLabeler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class VocabularyLabelerTest extends TestCase
{
    private VocabularyLabeler $labeler;

    protected function setUp(): void
    {
        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['launch.lift.chain' => 'Chain lift hill', 'launch.lim' => 'Magnetic launch'], 'en', 'database');
        $translator->addResource('array', ['launch.lift.chain' => 'Lift à chaîne'], 'fr', 'database');

        $this->labeler = new VocabularyLabeler($translator);
        \Locale::setDefault('en');
    }

    public function testCountryIsLabelledInTheGivenLocale(): void
    {
        $this->assertSame('Allemagne', $this->labeler->country('DE', 'Germany', 'fr'));
        $this->assertSame('Deutschland', $this->labeler->country('DE', 'Germany', 'de'));
        $this->assertSame('Alemania', $this->labeler->country('DE', 'Germany', 'es'));
    }

    public function testCountryDefaultsToTheCurrentLocale(): void
    {
        \Locale::setDefault('fr');

        $this->assertSame('États-Unis', $this->labeler->country('US', 'United States'));
    }

    public function testUserAssignedCodeIsLabelled(): void
    {
        $this->assertSame('Kosovo', $this->labeler->country('XK', 'Kosovo (name)', 'fr'));
    }

    /** The "unknown" row of the stats has no code. */
    public function testCountryWithoutCodeKeepsItsName(): void
    {
        $this->assertSame('Inconnu', $this->labeler->country(null, 'Inconnu', 'fr'));
    }

    public function testCountryWithUnknownCodeKeepsItsName(): void
    {
        $this->assertSame('Nowhere', $this->labeler->country('ZZ', 'Nowhere', 'fr'));
    }

    public function testTermIsTranslatedByItsCode(): void
    {
        $this->assertSame('Lift à chaîne', $this->labeler->term('launch.lift.chain', 'Chain lift hill', 'fr'));
    }

    public function testTermFallsBackToTheEnglishTranslation(): void
    {
        $this->assertSame('Magnetic launch', $this->labeler->term('launch.lim', 'LSM', 'fr'));
    }

    public function testTermWithoutTranslationKeepsItsName(): void
    {
        $this->assertSame('Flywheel', $this->labeler->term('launch.unknown', 'Flywheel', 'fr'));
    }

    public function testTermWithoutCodeKeepsItsName(): void
    {
        $this->assertSame('Ski Lift', $this->labeler->term(null, 'Ski Lift', 'fr'));
    }

    public function testLabelReadsTheEntity(): void
    {
        $country = new Country()->setCode('ES');

        $this->assertSame('Spain', $country->getName());
        $this->assertSame('Espagne', $this->labeler->label($country, 'fr'));
        $this->assertSame('Lift à chaîne', $this->labeler->label(new Launch()->setCode('launch.lift.chain')->setName('Chain lift hill'), 'fr'));
    }

    public function testSortByLabelFollowsTheLocaleAlphabet(): void
    {
        $sorted = $this->labeler->sortByLabel(
            [['name' => 'Suède'], ['name' => 'États-Unis'], ['name' => 'espagne'], ['name' => 'Allemagne']],
            static fn (array $row): string => $row['name'],
            'fr',
        );

        $this->assertSame(['Allemagne', 'espagne', 'États-Unis', 'Suède'], array_column($sorted, 'name'));
    }
}
