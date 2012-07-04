# Chat bundle for Symfony2

Depends on [BriareosNodejsBundle](https://github.com/Briareos/BriareosNodejsBundle).

## Instructions

1.  Your user class must implement `Briareos\ChatBundle\Entity\ChatSubjectInterface`

1.  Map the implemented interface to your user class.

        # app/config/config.yml
        doctrine:
            orm:
                resolve_target_entities
                    Briareos\NodejsBundle\Entity\NodejsSubjectInterface: App\UserBundle\Entity\User

1.  Register the `FIND_IN_SET` MySQL function.

        # app/config/config.yml
        dql:
            string_functions:
                FindInSet: DoctrineExtensions\Query\Mysql\FindInSet

1.  Update your schema.

        $ php app/console doctrine:schema:update --force

1.  On the pages you want to use the chat include `BriareosChatBundle:Chat:chat.html.twig` or write your own template
    and/or implementation. This must be done **after** the inclusion of `bundles/briareosnodejs/js/nodejs.js` from the
    *BriareosNodejsBundle*.
