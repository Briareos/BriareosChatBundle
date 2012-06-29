<?php

namespace Briareos\ChatBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Briareos\ChatBundle\Entity\ChatSubjectInterface;

/**
 * Briareos\ChatBundle\Entity\ChatState
 */
class ChatState
{
    /**
     * @var string $openConversations
     */
    private $openConversations;

    /**
     * @var ChatSubjectInterface
     */
    private $subject;

    /**
     * @var ChatSubjectInterface
     */
    private $activeConversation;


    public function __construct(ChatSubjectInterface $subject)
    {
        $this->setSubject($subject);
        $this->setOpenConversations();
    }

    /**
     * Set openConversations
     *
     * @param array $openConversations
     * @return ChatState
     */
    public function setOpenConversations(array $openConversations = array())
    {
        $this->openConversations = array(0);
        foreach ($openConversations as $openConversation) {
            $this->addOpenConversation($openConversation);
        }
        return $this;
    }

    /**
     * Get openConversations
     *
     * @return array
     */
    public function getOpenConversations()
    {
        $openConversations = explode(',', $this->openConversations);
        // We should do this step for proper JSON conversion that may occur.
        $openConversations = array_map('intval', $openConversations);
        array_shift($openConversations);
        return $this->openConversations;
    }

    public function addOpenConversation($conversationNumber)
    {
        $openConversations = $this->getOpenConversations();
        if (!is_numeric($conversationNumber) || (is_numeric($conversationNumber) && ($conversationNumber <= 0))) {
            throw new \Exception('Conversation number must be a number greater than zero.');
        }
        $key = array_search($conversationNumber, $openConversations);
        if ($key === false) {
            $openConversations[] = intval($conversationNumber);
            $this->setOpenConversations($openConversations);
        }
        return $this;
    }

    public function removeOpenConversation($conversationNumber)
    {
        $openConversations = $this->getOpenConversations();
        $key = array_search($conversationNumber, $openConversations);
        if (false !== $key) {
            unset($openConversations[$key]);
            $this->setOpenConversations($openConversations);
        }
        return $this;
    }

    /**
     * Set subject
     *
     * @param ChatSubjectInterface $subject
     * @return ChatState
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
     * Set activeConversation
     *
     * @param ChatSubjectInterface $activeConversation
     * @return ChatState
     */
    public function setActiveConversation(ChatSubjectInterface $activeConversation = null)
    {
        $this->activeConversation = $activeConversation;
        return $this;
    }

    /**
     * Get activeConversation
     *
     * @return ChatSubjectInterface
     */
    public function getActiveConversation()
    {
        return $this->activeConversation;
    }

    public function getActiveConversationId()
    {
        $activeConversation = $this->getActiveConversation();
        if($activeConversation === null) {
            return null;
        }
        return $activeConversation->getId();
    }
}