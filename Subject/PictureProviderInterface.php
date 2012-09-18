<?php

namespace Briareos\ChatBundle\Subject;

use Briareos\ChatBundle\Entity\ChatSubjectInterface;

interface PictureProviderInterface
{
    public function getSubjectPicture(ChatSubjectInterface $subject);
}
