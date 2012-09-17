<?php

namespace Briareos\ChatBundle\Subject;

use Briareos\ChatBundle\Entity\ChatSubjectInterface;

interface PresenceProviderInterface
{
    public function getPresentSubjects(ChatSubjectInterface $subject);
}
