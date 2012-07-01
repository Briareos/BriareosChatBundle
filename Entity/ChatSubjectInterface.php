<?php

namespace Briareos\ChatBundle\Entity;

use Briareos\NodejsBundle\Entity\NodejsSubjectInterface;

interface ChatSubjectInterface extends NodejsSubjectInterface
{
    public function getChatName();
}