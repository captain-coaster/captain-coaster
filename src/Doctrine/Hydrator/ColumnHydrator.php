<?php

declare(strict_types=1);

namespace App\Doctrine\Hydrator;

use Doctrine\ORM\Internal\Hydration\AbstractHydrator;

class ColumnHydrator extends AbstractHydrator
{
    protected function hydrateAllData()
    {
        return $this->_stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
