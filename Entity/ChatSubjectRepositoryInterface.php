<?php

namespace Briareos\ChatBundle\Entity;

use Briareos\ChatBundle\Entity\ChatSubjectInterface;

interface ChatSubjectRepositoryInterface
{
    public function getPresentSubjects(ChatSubjectInterface $subject);
}