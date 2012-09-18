<?php

namespace Briareos\ChatBundle\Subject;

use Briareos\ChatBundle\Subject\PictureProviderInterface;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

class PlaceholditPictureProvider implements PictureProviderInterface
{
    private $dimensions;

    public function __construct($dimensions)
    {
        $this->dimensions = $dimensions;
    }

    public function getSubjectPicture(ChatSubjectInterface $subject)
    {
        return sprintf('http://placehold.it/%s', $this->dimensions);
    }
}
