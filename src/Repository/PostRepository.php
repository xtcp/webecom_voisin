<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Post;
use App\Entity\User;
/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     *  Recupére les publications qui peut être visualisée par un utilisateur (publiques et des amis de l'utilisateur connectée)
     *    pour afficher dans la page principale
     */
    public function findForFeed(array $friendIds, string $filter = 'all', ?int $currentUserId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user_id', 'u')->addSelect('u')
            ->orderBy('p.datetime_creation', \SortDirection::Descending);

        $friendAndSelfIds = $currentUserId ? [...$friendIds, $currentUserId] : $friendIds;

        if ($filter === 'public') {
            $qb->andWhere('p.visibility = true');
        } elseif ($filter === 'friends') {
            $qb->andWhere('p.visibility = false')
                ->andWhere('u.id IN (:friendAndSelfIds)')
                ->setParameter('friendAndSelfIds', $friendAndSelfIds ?: [0]);
        } else {
            $qb->andWhere('p.visibility = true OR (p.visibility = false AND u.id IN (:friendAndSelfIds))')
                ->setParameter('friendAndSelfIds', $friendAndSelfIds ?: [0]);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     *  Recupére les publications qui peut être visualisée par un utilisateur (propres posts, de ces amis)
     */
    public function findVisibleForProfile(User $owner, bool $viewerIsFriend, bool $viewerIsOwner): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.user_id = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.datetime_creation', \SortDirection::Ascending);

        if (!$viewerIsOwner && !$viewerIsFriend) {
            $qb->andWhere('p.visibility = true');
        }

        return $qb->getQuery()->getResult();
    }
    
}
