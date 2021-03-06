<?php

namespace Briareos\ChatBundle\Subject;

use Briareos\ChatBundle\Entity\ChatSubjectInterface;
use Doctrine\ORM\EntityManager;

class BasicPresenceProvider
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function getPresentSubjects(ChatSubjectInterface $subject)
    {
        $presences = $this->em
          ->createQuery('Select p From BriareosNodejsBundle:NodejsPresence p Inner Join p.subject s Where p.subject <> :subject And p.subject Is Not Null Group By p.subject Order By p.seenAt Desc')
          ->setParameter('subject', $subject)
          ->execute();
        $subjects = array();
        /** @var $presence \Briareos\NodejsBundle\Entity\NodejsPresence */
        foreach ($presences as $presence) {
            $subjects[] = $presence->getSubject();
        }

        return $subjects;
    }
}
