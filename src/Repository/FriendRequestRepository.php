<?php

namespace App\Repository;

use App\Entity\FriendRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FriendRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FriendRequest::class);
    }
    /** 
     *    Verifier si une demande d'ami existe entre deux utilisateurs
     */
    public function findBetween(User $a, User $b): ?FriendRequest
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('(fr.sender = :a AND fr.receiver = :b) OR (fr.sender = :b AND fr.receiver = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** 
     *    Recuperer les demandes en attente d'un utilisateur
     */
    public function findPendingReceivedBy(User $user): array
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('fr.receiver = :user')
            ->andWhere('fr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', FriendRequest::STATUS_PENDING)
            ->orderBy('fr.datetime', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }
}
