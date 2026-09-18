<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fictional ratings/reviews (see AGENTS.md's real-vs-fictional fixtures split) --
 * just enough to populate a coaster page and give the ranking command real
 * numbers to chew on, not a realistic vote distribution.
 *
 * @phpstan-type RatingSpec array{
 *     user: string, coaster: string, rating: float, language: string,
 *     review: ?string, pros: array<string>, cons: array<string>,
 * }
 */
final class RiddenCoasterFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var list<RatingSpec> */
    private const array RATINGS = [
        ['user' => 'alex', 'coaster' => 'steel_vengeance', 'rating' => 5.0, 'language' => 'en', 'review' => "Best hybrid coaster I've ridden -- relentless airtime from start to finish.", 'pros' => ['airtimes', 'ejectors'], 'cons' => []],
        ['user' => 'alex', 'coaster' => 'millennium_force', 'rating' => 4.5, 'language' => 'en', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'alex', 'coaster' => 'blue_fire', 'rating' => 4.0, 'language' => 'en', 'review' => 'Smooth launch and a fun layout, though a bit short.', 'pros' => ['launch'], 'cons' => ['short']],
        ['user' => 'alex', 'coaster' => 'x2', 'rating' => 4.5, 'language' => 'en', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'alex', 'coaster' => 'tatsu', 'rating' => 4.0, 'language' => 'en', 'review' => null, 'pros' => [], 'cons' => []],

        ['user' => 'julie', 'coaster' => 'steel_vengeance', 'rating' => 5.0, 'language' => 'fr', 'review' => "Un mélange parfait entre le bois et l'acier, aucun temps mort.", 'pros' => ['layout'], 'cons' => []],
        ['user' => 'julie', 'coaster' => 'silver_star', 'rating' => 4.0, 'language' => 'fr', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'julie', 'coaster' => 'wodan', 'rating' => 3.5, 'language' => 'fr', 'review' => 'Sympa mais un peu répétitif sur la fin.', 'pros' => [], 'cons' => ['pace']],
        ['user' => 'julie', 'coaster' => 'toutatis', 'rating' => 4.5, 'language' => 'fr', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'julie', 'coaster' => 'oziris', 'rating' => 4.0, 'language' => 'fr', 'review' => 'Belle inversion au-dessus de l\'eau, très immersif.', 'pros' => ['theming'], 'cons' => []],

        ['user' => 'marta', 'coaster' => 'shambhala', 'rating' => 4.5, 'language' => 'es', 'review' => 'Una bajada impresionante, se siente cada segundo.', 'pros' => ['speed'], 'cons' => []],
        ['user' => 'marta', 'coaster' => 'dragon_khan', 'rating' => 3.5, 'language' => 'es', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'marta', 'coaster' => 'oziris', 'rating' => 4.0, 'language' => 'es', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'marta', 'coaster' => 'silver_star', 'rating' => 4.5, 'language' => 'es', 'review' => 'Un hyper coaster clásico, muy bien mantenido.', 'pros' => ['comfort'], 'cons' => []],

        ['user' => 'lukas', 'coaster' => 'blue_fire', 'rating' => 4.5, 'language' => 'de', 'review' => 'Ein toller Launch-Coaster mit viel Tempo.', 'pros' => ['speed'], 'cons' => []],
        ['user' => 'lukas', 'coaster' => 'wodan', 'rating' => 4.0, 'language' => 'de', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'lukas', 'coaster' => 'silver_star', 'rating' => 4.5, 'language' => 'de', 'review' => null, 'pros' => [], 'cons' => []],
        ['user' => 'lukas', 'coaster' => 'millennium_force', 'rating' => 5.0, 'language' => 'de', 'review' => 'Auch nach 25 Jahren noch einer der besten Coaster der Welt.', 'pros' => ['layout', 'ejectors'], 'cons' => []],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::RATINGS as $spec) {
            $ridden = new RiddenCoaster()
                ->setUser($this->getReference('user_'.$spec['user'], User::class))
                ->setCoaster($this->getReference('coaster_'.$spec['coaster'], Coaster::class))
                ->setValue($spec['rating'])
                ->setLanguage($spec['language'])
                ->setReview($spec['review']);

            if (null !== $spec['review']) {
                $ridden->setModeratedAt(new \DateTime());
            }

            foreach ($spec['pros'] as $pro) {
                $ridden->addPro($this->getReference('tag_pro_'.$pro, Tag::class));
            }

            foreach ($spec['cons'] as $con) {
                $ridden->addCon($this->getReference('tag_con_'.$con, Tag::class));
            }

            $manager->persist($ridden);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, CoasterFixtures::class, TagFixtures::class];
    }
}
