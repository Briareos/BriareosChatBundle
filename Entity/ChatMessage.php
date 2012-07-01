<?php

namespace Briareos\ChatBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

/**
 * Briareos\ChatBundle\Entity\ChatMessage
 */
class ChatMessage
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var string $text
     */
    private $text;

    /**
     * @var \DateTime $createdAt
     */
    private $createdAt;

    /**
     * @var ChatSubjectInterface
     */
    private $sender;

    /**
     * @var ChatSubjectInterface
     */
    private $receiver;


    public function __construct()
    {
        $this->setCreatedAt(new \DateTime());
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set text
     *
     * @param string $text
     * @return ChatMessage
     */
    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return ChatMessage
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set sender
     *
     * @param ChatSubjectInterface $sender
     * @return ChatMessage
     */
    public function setSender(ChatSubjectInterface $sender)
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * Get sender
     *
     * @return ChatSubjectInterface
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Set receiver
     *
     * @param ChatSubjectInterface $receiver
     * @return ChatMessage
     */
    public function setReceiver(ChatSubjectInterface $receiver)
    {
        $this->receiver = $receiver;
        return $this;
    }

    /**
     * Get receiver
     *
     * @return ChatSubjectInterface
     */
    public function getReceiver()
    {
        return $this->receiver;
    }
}