<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fictional accounts, one per supported locale -- unlike parks/coasters/taxonomy,
 * user data is never real (see AGENTS.md's real-vs-fictional fixtures split).
 */
final class UserFixtures extends Fixture
{
    /** firstName, lastName, email, preferredLocale */
    private const array USERS = [
        'alex' => ['Alex', 'Morgan', 'alex.morgan@example.com', 'en'],
        'julie' => ['Julie', 'Lambert', 'julie.lambert@example.com', 'fr'],
        'marta' => ['Marta', 'Garcia', 'marta.garcia@example.com', 'es'],
        'lukas' => ['Lukas', 'Weber', 'lukas.weber@example.com', 'de'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $key => [$firstName, $lastName, $email, $locale]) {
            $user = new User()
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setPreferredLocale($locale);
            $user->setEmail($email);
            $user->setEnabled(true);

            $manager->persist($user);
            $this->addReference('user_'.$key, $user);
        }

        $manager->flush();
    }
}
