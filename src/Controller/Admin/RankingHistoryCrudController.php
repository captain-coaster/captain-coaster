<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RankingHistory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class RankingHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RankingHistory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ranking History')
            ->setEntityLabelInPlural('Ranking History')
            ->setSearchFields(['id', 'coaster.id', 'coaster.name'])
            ->setDefaultSort(['ranking' => 'DESC', 'rank' => 'ASC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(100);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable('edit')
            ->disable('delete')
            ->disable('new');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('coaster'),
            IntegerField::new('rank'),
            NumberField::new('score'),
            NumberField::new('validDuels'),
            NumberField::new('totalTopsIn'),
            NumberField::new('averageTopRank'),
            NumberField::new('totalRatings'),
            NumberField::new('averageRating'),
            DateField::new('ranking.computedAt', 'Ranking Date'),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('coaster')
            ->add('rank')
            ->add('score')
            ->add('validDuels')
            ->add(DateTimeFilter::new('ranking'));
    }
}
