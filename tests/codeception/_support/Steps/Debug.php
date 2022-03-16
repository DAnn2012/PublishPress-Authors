<?php

namespace Steps;


trait Debug
{
    /**
     * @Then I take a screenshot named :name
     */
    public function iTakeAScreenshotNamed($name)
    {
        $this->makeScreenshot($name);
    }

    /**
     * @Then I wait for :time seconds
     */
    public function waitForSeconds($time)
    {
        $this->wait((int)$time);
    }

    /**
     * @Given I pause
     * @When I pause
     * @Then I pause
     */
    public function pause()
    {
        $this->pause();
    }
}
