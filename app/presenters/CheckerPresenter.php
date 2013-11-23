<?php

namespace App;

use Nette,
    Model,
    Nette\Application\Responses\TextResponse,
    Nette\Diagnostics\Debugger;

/**
 * Homepage presenter.
 */
class CheckerPresenter extends BasePresenter {

    /** @var Model\Model @inject */
    public $model;

    /** @var boolean */
    private $cli;

    public function startup() {
	parent::startup();
	$this->cli = $this->getContext()->parameters['consoleMode'];
    }

    public function actionDefault($l = 200, $d = NULL, $sd = "false") {
	if (!$this->cli) {
	    echo "Lze spustit pouze v konzoli.\n";
	    $this->terminate();
	}
	Debugger::timer();

	$this->model->check($l, $d, $sd);

	printf("%1.3fs\n", Debugger::timer());

	$this->sendResponse(new TextResponse(""));
    }

}
