<?php

namespace Briareos\ChatBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * ChatConversationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ChatConversationRepository extends EntityRepository
{
    public function getConversation(ChatSubjectInterface $subject, ChatSubjectInterface $partner)
    {
        $em = $this->getEntityManager();
        /** @var $conversation ChatConversation */
        $conversation = $this->findOneBy(array(
            'subject' => $subject,
            'partner' => $partner,
        ));

        if ($conversation === null) {
            $conversation = new ChatConversation();
            $conversation->setSubject($subject);
            $conversation->setPartner($partner);
            $em->persist($conversation);

            $reverseConversation = new ChatConversation();
            $reverseConversation->setSubject($partner);
            $reverseConversation->setPartner($subject);
            $em->persist($reverseConversation);

            $em->flush($conversation);
            $em->flush($reverseConversation);
        }
        return $conversation;
    }
}