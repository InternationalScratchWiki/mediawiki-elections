<?php
class SpecialElectionResults extends SpecialPage {
	function __construct() {
		parent::__construct('ElectionResults', 'viewelectionresults');
	}

	function execute($par) {
		global $wgElectionActive, $wgElectionId, $wgElectionCandidates, $wgElectionCountMethod;

		$output = $this->getOutput();
		$output->setPageTitle($this->msg('election-viewelectionresults-title')->escaped());

		$this->checkPermissions();

		$voteLoader = new ElectionVoteLoader(__METHOD__, $wgElectionId);
		$electionResults = $voteLoader->getResults($wgElectionCandidates);
		$results = $electionResults['results'];
		$voteCounts = $electionResults['voteCounts'];

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
		switch ($wgElectionCountMethod) {
			case 'confidence':
				$resultKey = 'result';
				break;
			case 'borda':
			default:
				$resultKey = 'score';
		}
		$output->addHTML(Html::element(
			'th', ['scope' => 'col'],
			$this->msg('election-viewelectionresults-' . $resultKey)->text()
		));

		$output->addHTML(Html::closeElement('tr'));
		$output->addHTML(Html::closeElement('thead'));
		foreach ($wgElectionCandidates as $candidate) {
			$score = $results[$candidate] ?? 0;
			$voteCount = $voteCounts[$candidate] ?? 0;
			$output->addHTML(Html::openElement('tr'));

			$output->addHTML(Html::element('th', ['scope' => 'row'], $candidate));
			switch ($wgElectionCountMethod) {
				case 'confidence':
					if ($score >= $voteCount / 2) {
						$key = 'election-viewelectionresults-yes';
					} else {
						$key = 'election-viewelectionresults-no';
					}
					break;
				case 'borda':
				default:
					$key = 'election-viewelectionresults-score-display';
			}
			$output->addHTML(Html::element('td', [], $this->msg($key)->params(
				$score, $voteCount
			)));

			$output->addHTML(Html::closeElement('tr'));
		}
		$output->addHTML(Html::closeElement('table'));
	}
}