{**
 * templates/reviewer/review/reviewRoundTab.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Build reviewer review round info buttons
 *}

<div class="ui-tabs-nav" style="padding-bottom: 1em;">
	{translate key="reviewer.submission.reviewRound.info"}&nbsp;
	{foreach from=$reviewRounds item=reviewRound key=key}
		{if $reviewRound->getRound() != $lastReviewRoundNumber && isset($reviewRoundActions.$key)}
			{assign var=resumeButtonId value="resumeButton"|concat:"-"|uniqid}
			{include file="linkAction/buttonGenericLinkAction.tpl" buttonSelector="#"|concat:$resumeButtonId action=$reviewRoundActions.$key}
			<a href="#" class="pkp_button" id="{$resumeButtonId}">{translate key="submission.round" round=$reviewRound->getRound()}</a>
		{/if}
	{/foreach}
</div>
