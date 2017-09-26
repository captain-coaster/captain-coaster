<?php

namespace BddBundle\Doctrine;

use BddBundle\Entity\BuiltCoaster;
use BddBundle\Entity\Type;
use Doctrine\ORM\Event\LifecycleEventArgs;

class BuiltCoasterListener
{
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof BuiltCoaster) {
            $this->handleEvent($entity);
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof BuiltCoaster) {
            $this->handleEvent($entity);
        }
    }

    private function handleEvent(BuiltCoaster $builtCoaster)
    {
        // @todo: faire mieux ?
        $filteredTypes = $builtCoaster->getTypes()->filter(function (Type $type) {
           return $type->getSlug() === 'kiddie';
        });

        $builtCoaster->setIsKiddie(false);

        if ($filteredTypes->count() === 1) {
            $builtCoaster->setIsKiddie(true);
        }

        return $this;
    }
}