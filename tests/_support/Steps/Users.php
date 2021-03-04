<?php

namespace Steps;


use Behat\Gherkin\Node\TableNode;

trait Users
{
    /**
     * @Then I see the user ID for :userLogin in the deletion list
     */
    public function ISeeTheUserIDForUserInTheDeletionList($userLogin)
    {
        $user = get_user_by('login', $userLogin);

        $this->see("ID #{$user->ID}");
    }

    /**
     * @Given the user :userLogin exists with role :userRole
     */
    public function theUserExistsWithRole($userLogin, $userRole)
    {
        $this->factory()->user->create(
            [
                'user_login' => $userLogin,
                'user_pass'  => $userLogin,
                'user_email' => sprintf('%s@example.com', $userLogin),
                'role'       => $userRole
            ]
        );
    }

    /**
     * @Given following users exist
     */
    public function followingUsersExist(TableNode $users)
    {
        foreach ($users->getRows() as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $this->factory()->user->create(
                [
                    'user_login' => $row[0],
                    'user_pass'  => $row[0],
                    'user_email' => $row[0] . '@example.com',
                    'role'       => $row[1]
                ]
            );
        }
    }

    /**
     * @Given the user :userLogin is selected
     */
    public function theUserIsSelected($userLogin)
    {
        $user = get_user_by('login', $userLogin);

        $this->checkOption('#user_' . $user->ID);
    }
}
