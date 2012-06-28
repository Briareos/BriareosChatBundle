<?php

namespace Briareos\ChatBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

/**
 * Briareos\ChatBundle\Entity\ChatConversation
 */
class ChatConversation
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var integer $lastMessageRead
     */
    private $lastMessageRead;

    /**
     * @var integer $lastMessageCleared
     */
    private $lastMessageCleared;

    /**
     * @var ChatSubjectInterface
     */
    private $subject;

    /**
     * @var ChatSubjectInterface
     */
    private $partner;


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
     * Set lastMessageRead
     *
     * @param integer $lastMessageRead
     * @return ChatConversation
     */
    public function setLastMessageRead($lastMessageRead)
    {
        $this->lastMessageRead = $lastMessageRead;
        return $this;
    }

    /**
     * Get lastMessageRead
     *
     * @return integer 
     */
    public function getLastMessageRead()
    {
        return $this->lastMessageRead;
    }

    /**
     * Set lastMessageCleared
     *
     * @param integer $lastMessageCleared
     * @return ChatConversation
     */
    public function setLastMessageCleared($lastMessageCleared)
    {
        $this->lastMessageCleared = $lastMessageCleared;
        return $this;
    }

    /**
     * Get lastMessageCleared
     *
     * @return integer 
     */
    public function getLastMessageCleared()
    {
        return $this->lastMessageCleared;
    }

    /**
     * Set subject
     *
     * @param ChatSubjectInterface $subject
     * @return ChatConversation
     */
    public function setSubject(ChatSubjectInterface $subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Get subject
     *
     * @return ChatSubjectInterface
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set partner
     *
     * @param ChatSubjectInterface $partner
     * @return ChatConversation
     */
    public function setPartner(ChatSubjectInterface $partner)
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * Get partner
     *
     * @return ChatSubjectInterface
     */
    public function getPartner()
    {
        return $this->partner;
    }
}