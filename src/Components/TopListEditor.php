<?php

declare(strict_types=1);

namespace App\Components;

use App\Entity\Coaster;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class TopListEditor extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp]
    public int $topId;

    #[LiveProp]
    public string $locale = 'en';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getTop(): Top
    {
        $top = $this->em->getRepository(Top::class)->find($this->topId);
        if (!$top instanceof Top) {
            throw new NotFoundHttpException('Top not found.');
        }
        $this->denyAccessUnlessGranted('edit', $top);

        return $top;
    }

    /** @return array<int, TopCoaster> sorted by position asc */
    public function getItems(): array
    {
        $items = $this->getTop()->getTopCoasters()->toArray();
        usort($items, static fn (TopCoaster $a, TopCoaster $b) => $a->getPosition() <=> $b->getPosition());

        return $items;
    }

    /** @return list<int> */
    private function getCurrentCoasterIds(): array
    {
        $ids = [];
        foreach ($this->getTop()->getTopCoasters() as $tc) {
            $id = $tc->getCoaster()->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @return list<array<string, mixed>> */
    public function getResults(): array
    {
        if (mb_strlen($this->query) < 2) {
            return [];
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            return [];
        }
        $raw = $this->em->getRepository(Coaster::class)
            ->suggestCoasterForTop($this->query, $user);

        return self::flagDuplicates($raw, $this->getCurrentCoasterIds());
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param list<int>                        $existingIds
     *
     * @return list<array<string, mixed>>
     */
    public static function flagDuplicates(array $items, array $existingIds): array
    {
        $existing = array_flip($existingIds);

        return array_values(array_map(
            static fn (array $item) => array_merge($item, ['alreadyInList' => isset($existing[$item['id']])]),
            $items
        ));
    }

    #[LiveAction]
    public function addCoaster(#[LiveArg] int $coasterId): void
    {
        $top = $this->getTop();
        if (\in_array($coasterId, $this->getCurrentCoasterIds(), true)) {
            return;
        }
        $coaster = $this->em->getRepository(Coaster::class)->find($coasterId);
        if (!$coaster instanceof Coaster) {
            return;
        }
        $maxPos = 0;
        foreach ($top->getTopCoasters() as $tc) {
            $maxPos = max($maxPos, $tc->getPosition());
        }
        $topCoaster = new TopCoaster();
        $topCoaster->setCoaster($coaster)->setPosition($maxPos + 1);
        $top->addTopCoaster($topCoaster);
        $this->em->persist($topCoaster);
        $this->em->flush();
        $this->query = '';
    }

    #[LiveAction]
    public function removeCoaster(#[LiveArg] int $coasterId): void
    {
        $top = $this->getTop();
        foreach ($top->getTopCoasters() as $tc) {
            if ($tc->getCoaster()->getId() === $coasterId) {
                $top->removeTopCoaster($tc);
                $this->em->remove($tc);
                $this->em->flush();
                $this->resequence($top);

                return;
            }
        }
    }

    #[LiveAction]
    public function reorder(#[LiveArg] string $positions): void
    {
        $top = $this->getTop();
        $map = json_decode($positions, true);
        if (!\is_array($map)) {
            return;
        }
        foreach ($top->getTopCoasters() as $tc) {
            $key = (string) $tc->getCoaster()->getId();
            if (isset($map[$key])) {
                $tc->setPosition((int) $map[$key]);
            }
        }
        $this->em->flush();
    }

    #[LiveAction]
    public function moveToTop(#[LiveArg] int $coasterId): void
    {
        $this->doMove($coasterId, 0);
    }

    #[LiveAction]
    public function moveToBottom(#[LiveArg] int $coasterId): void
    {
        $top = $this->getTop();
        $count = $top->getTopCoasters()->count();
        $this->doMove($coasterId, $count - 1);
    }

    #[LiveAction]
    public function moveToPosition(#[LiveArg] int $coasterId, #[LiveArg] int $position): void
    {
        $top = $this->getTop();
        $count = $top->getTopCoasters()->count();
        $this->doMove($coasterId, max(0, min($position - 1, $count - 1)));
    }

    private function doMove(int $coasterId, int $targetIndex): void
    {
        $top = $this->getTop();
        $items = $top->getTopCoasters()->toArray();
        usort($items, static fn (TopCoaster $a, TopCoaster $b) => $a->getPosition() <=> $b->getPosition());

        $movingItem = null;
        $remaining = [];
        foreach ($items as $tc) {
            if ($tc->getCoaster()->getId() === $coasterId) {
                $movingItem = $tc;
            } else {
                $remaining[] = $tc;
            }
        }
        if (!$movingItem instanceof TopCoaster) {
            return;
        }
        array_splice($remaining, $targetIndex, 0, [$movingItem]);
        foreach ($remaining as $i => $tc) {
            $tc->setPosition($i + 1);
        }
        $this->em->flush();
    }

    private function resequence(Top $top): void
    {
        $items = $top->getTopCoasters()->toArray();
        usort($items, static fn (TopCoaster $a, TopCoaster $b) => $a->getPosition() <=> $b->getPosition());
        foreach ($items as $i => $tc) {
            $tc->setPosition($i + 1);
        }
        $this->em->flush();
    }
}
