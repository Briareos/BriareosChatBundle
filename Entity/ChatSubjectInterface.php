<?php

namespace Briareos\ChatBundle\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

interface ChatSubjectInterface extends UserInterface
{
    public function getId();

    public function getChatName();
}