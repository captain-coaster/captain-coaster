<?php

declare(strict_types=1);

namespace App\Tooling\Translation\EventListener;

use App\Tooling\LocaleProvider;
use App\Tooling\Translation\Model\TranslatableInterface;
use App\Tooling\Translation\Model\TranslationInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

#[AsDoctrineListener(event: Events::loadClassMetadata, connection: 'default')]
#[AsDoctrineListener(event: Events::postLoad, connection: 'default')]
class TranslatableListener implements EventSubscriber
{
    public function __construct(private LocaleProvider $localeProvider)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
            Events::postLoad,
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $reflection = $classMetadata->getReflectionClass();

        if ($reflection->isAbstract()) {
            return;
        }

        if ($reflection->implementsInterface(TranslatableInterface::class)) {
            $this->mapTranslatable($classMetadata);
        }

        if ($reflection->implementsInterface(TranslationInterface::class)) {
            $this->mapTranslation($classMetadata);
        }
    }

    private function mapTranslatable(ClassMetadata $metadata): void
    {
        $className = $metadata->name;

        if (!$metadata->hasAssociation('translations')) {
            $metadata->mapOneToMany([
                'fieldName' => 'translations',
                'targetEntity' => $className::getTranslationEntityClass(),
                'mappedBy' => 'translatable',
                'fetch' => ClassMetadata::FETCH_EAGER,
                'indexBy' => 'locale',
                'cascade' => ['persist', 'remove', 'detach', 'refresh'],
                'orphanRemoval' => true,
            ]);
        }
    }

    private function mapTranslation(ClassMetadata $metadata): void
    {
        $className = $metadata->name;

        if (!$metadata->hasAssociation('translatable')) {
            $metadata->mapManyToOne([
                'fieldName' => 'translatable',
                'targetEntity' => $className::getTranslatableEntityClass(),
                'inversedBy' => 'translations',
                'fetch' => ClassMetadata::FETCH_EAGER,
                'joinColumns' => [[
                    'name' => 'translatable_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                    'nullable' => false,
                ]],
            ]);
        }

        if (!$metadata->hasField('locale')) {
            $metadata->mapField([
                'fieldName' => 'locale',
                'type' => 'string',
                'nullable' => false,
            ]);
        }

        // Map unique index.
        $columns = [
            $metadata->getSingleAssociationJoinColumnName('translatable'),
            'locale',
        ];

        if (!$this->hasUniqueConstraint($metadata, $columns)) {
            $constraints = $metadata->table['uniqueConstraints'] ?? [];

            $constraints[$metadata->getTableName().'_uniq_trans'] = [
                'columns' => $columns,
            ];

            $metadata->setPrimaryTable([
                'uniqueConstraints' => $constraints,
            ]);
        }
    }

    private function hasUniqueConstraint(ClassMetadata $metadata, array $columns): bool
    {
        if (!isset($metadata->table['uniqueConstraints'])) {
            return false;
        }

        foreach ($metadata->table['uniqueConstraints'] as $constraint) {
            if (!array_diff($constraint['columns'], $columns)) {
                return true;
            }
        }

        return false;
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $entity->setCurrentLocale($this->localeProvider->provideCurrentLocale());
    }
}
