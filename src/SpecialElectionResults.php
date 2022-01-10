<?php
class SpecialElectionResults extends SpecialPage {
	function __construct() {
		parent::__construct('ElectionResults', 'viewelectionresults');
	}

	function execute($par) {
		global $wgElectionActive, $wgElectionId, $wgElectionCandidates;

		$output = $this->getOutput();
		$output->setPageTitle($this->msg('election-viewelectionresults-title')->escaped());

		$this->checkPermissions();

		$voteLoader = new ElectionVoteLoader(__METHOD__, $wgElectionId);
		$results = $voteLoader->getResults($wgElectionCandidates);

		if (empty($results)) {
			$output->addWikiMsg('election-viewelectionresults-empty');
			return;
		}

		$output->addWikiMsg('election-viewelectionresults-description');

		$output->addHTML(Html::openElement('table', ['class' => 'wikitable']));
		$output->addHTML(Html::openElement('thead'));
		$output->addHTML(Html::openElement('tr'));

		$output->addHTML(Html::element(
			'th', ['scope' => 'col'],
			$this->msg('election-viewelectionresults-candidate')->text()
		));
		$output->addHTML(Html::element(
			'th', ['scope' => 'col'],
			$this->msg('election-viewelectionresults-score')->text()
		));

		$output->addHTML(Html::closeElement('tr'));
		$output->addHTML(Html::closeElement('thead'));
		foreach ($results as $candidate => $score) {
			$output->addHTML(Html::openElement('tr'));

			$output->addHTML(Html::element('th', ['scope' => 'row'], $candidate));
			$output->addHTML(Html::element('td', [], $score));

			$output->addHTML(Html::closeElement('tr'));
		}
		$output->addHTML(Html::closeElement('table'));
	}
}