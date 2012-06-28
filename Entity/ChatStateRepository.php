<?php

namespace Briareos\ChatBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

/**
 * ChatStateRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ChatStateRepository extends EntityRepository
{
    public function getOpenStates(ChatSubjectInterface $subject)
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('FindInSet(:subject_id, s.openConversations)');
        $qb->setParameter(':subject_id', $subject->getId());
        return $qb->getQuery()->execute();
    }
}