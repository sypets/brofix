<?php

declare(strict_types=1);
namespace Sypets\Brofix\Task;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/**
 * This class provides Scheduler Additional Field plugin implementation
 * @internal This class is a specific Scheduler task implementation and is not part of the TYPO3's Core API.
 */
class ValidatorTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * Default language file of the extension brofix
     *
     * @var string
     */
    protected $languageFile = 'LLL:EXT:brofix/Resources/Private/Language/locallang.xlf';

    /**
     * Render additional information fields within the scheduler backend.
     *
     * @param array $taskInfo Array information of task to return
     * @param ValidatorTask|null $task The task object being edited. Null when adding a task!
     * @param SchedulerModuleController $schedulerModule Reference to the BE module of the Scheduler
     * @return array Additional fields
     * @see AdditionalFieldProviderInterface->getAdditionalFields
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];
        $currentSchedulerModuleAction = $schedulerModule->getCurrentAction();

        if (empty($taskInfo['configuration'])) {
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['configuration'] = $taskInfo['brofix']['configuration'];
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['configuration'] = $task->getOverrideTsConfigString();
            } else {
                $taskInfo['configuration'] = $task->getOverrideTsConfigString();
            }
        }

        if (empty($taskInfo['depth'])) {
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['depth'] = $taskInfo['brofix']['depth'];
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['depth'] = $task->getDepth();
            } else {
                $taskInfo['depth'] = $task->getDepth();
            }
        }

        if (empty($taskInfo['page'])) {
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['page'] = $taskInfo['brofix']['page'];
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['page'] = $task->getPage();
            } else {
                $taskInfo['page'] = $task->getPage();
            }
        }
        if (empty($taskInfo['email'])) {
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['email'] = $taskInfo['brofix']['email'];
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['email'] = $task->getEmail();
            } else {
                $taskInfo['email'] = $task->getEmail();
            }
        }
        if (empty($taskInfo['emailOnBrokenLinkOnly'])) {
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['emailOnBrokenLinkOnly'] = $taskInfo['brofix']['emailOnBrokenLinkOnly'] ?: 1;
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['emailOnBrokenLinkOnly'] = $task->getEmailOnBrokenLinkOnly();
            } else {
                $taskInfo['emailOnBrokenLinkOnly'] = $task->getEmailOnBrokenLinkOnly();
            }
        }
        if (empty($taskInfo['emailTemplateFile'])) {
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['emailTemplateFile'] = $taskInfo['brofix']['emailTemplateFile'] ?: 'EXT:brofix/Resources/Private/Templates/mailtemplate.html';
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['emailTemplateFile'] = $task->getEmailTemplateFile();
            } else {
                $taskInfo['emailTemplateFile'] = $task->getEmailTemplateFile();
            }
        }

        // page
        $fieldId = 'task_page';
        $fieldCode = '<input type="number" min="0" class="form-control" name="tx_scheduler[brofix][page]" id="'
            . $fieldId
            . '" value="'
            . htmlspecialchars((string)($taskInfo['page'] ?? ''))
            . '">';
        $lang = $this->getLanguageService();
        $label = $lang->sL($this->languageFile . ':tasks.validate.page');
        $pageTitle = '';
        if (!empty($taskInfo['page'])) {
            $pageTitle = $this->getPageTitle((int)$taskInfo['page']);
        }
        $additionalFields[$fieldId] = [
            'browser' => 'page',
            'pageTitle' => $pageTitle,
            'code' => $fieldCode,
            'label' => $label,
            'cshKey' => 'brofix',
            'cshLabel' => $fieldId,
        ];

        // depth
        $fieldId = 'task_depth';
        $fieldValueArray = [
            '0' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
            '1' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
            '2' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
            '3' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
            '4' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
            '999' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi')
        ];
        $fieldCode = '<select class="form-control" name="tx_scheduler[brofix][depth]" id="' . $fieldId . '">';
        foreach ($fieldValueArray as $depth => $label) {
            $fieldCode .= "\t" . '<option value="' . htmlspecialchars((string)$depth) . '"'
                . (($depth == $taskInfo['depth']) ? ' selected="selected"' : '') . '>'
                . $label
                . '</option>';
        }
        $fieldCode .= '</select>';
        $label = $lang->sL($this->languageFile . ':tasks.validate.depth');
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => $label,
            'cshKey' => 'brofix',
            'cshLabel' => $fieldId,
        ];

        // tsconfig
        $fieldId = 'task_configuration';
        $fieldCode = '<textarea class="form-control" name="tx_scheduler[brofix][configuration]" id="'
            . $fieldId
            . '" >'
            . htmlspecialchars($taskInfo['configuration'] ?? '')
            . '</textarea>';
        $label = $lang->sL($this->languageFile . ':tasks.validate.conf');
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => $label,
            'cshKey' => 'brofix',
            'cshLabel' => $fieldId,
        ];

        // email recipient
        $fieldId = 'task_email';
        $fieldCode = '<textarea class="form-control" rows="5" cols="50" name="tx_scheduler[brofix][email]" id="'
            . $fieldId
            . '">'
            . htmlspecialchars($taskInfo['email'] ?? '')
            . '</textarea>';
        $label = $lang->sL($this->languageFile . ':tasks.validate.email');
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => $label,
            'cshKey' => 'brofix',
            'cshLabel' => $fieldId,
        ];

        // checkbox - email on broken links only
        $fieldId = 'task_emailOnBrokenLinkOnly';
        $fieldCode = '<div class="checkbox"><label>'
            . '<input type="checkbox" name="tx_scheduler[brofix][emailOnBrokenLinkOnly]" id="' . $fieldId . '" '
            . (($taskInfo['emailOnBrokenLinkOnly'] ?? false) ? 'checked="checked"' : '')
            . '></label></div>';
        $label = $lang->sL($this->languageFile . ':tasks.validate.emailOnBrokenLinkOnly');
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => $label,
            'cshKey' => 'brofix',
            'cshLabel' => $fieldId,
        ];

        // template
        $fieldId = 'task_emailTemplateFile';
        $fieldCode = '<input class="form-control" type="text"  name="tx_scheduler[brofix][emailTemplateFile]" '
            . 'id="'
            . $fieldId
            . '" value="'
            . htmlspecialchars($taskInfo['emailTemplateFile'] ?? '')
            . '">';
        $label = $lang->sL($this->languageFile . ':tasks.validate.emailTemplateFile');
        $additionalFields[$fieldId] = [
            'code' => $fieldCode,
            'label' => $label,
            'cshKey' => 'brofix',
            'cshLabel' => $fieldId,
        ];
        return $additionalFields;
    }

    /**
     * Mark current value as selected by returning the "selected" attribute
     *
     * @param array $configurationArray Array of configuration
     * @param string $currentValue Value of selector object
     * @return string Html fragment for a selected option or empty
     */
    protected function getSelectedState(array $configurationArray, $currentValue)
    {
        $selected = '';
        if (in_array($currentValue, $configurationArray, true)) {
            $selected = 'selected="selected" ';
        }
        return $selected;
    }

    /**
     * This method checks any additional data that is relevant to the specific task.
     * If the task class is not relevant, the method is expected to return TRUE.
     *
     * @param array $submittedData Reference to the array containing the data submitted by the user
     * @param SchedulerModuleController $schedulerModule Reference to the BE module of the Scheduler
     * @return bool TRUE if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule)
    {
        $isValid = true;
        // @todo add validation to validate the $submittedData['configuration']
        // @todo which is normally a comma separated string
        $lang = $this->getLanguageService();
        if (!empty($submittedData['brofix']['email'])) {
            if (strpos($submittedData['brofix']['email'], ',') !== false) {
                $emailList = GeneralUtility::trimExplode(',', $submittedData['brofix']['email']);
            } else {
                $emailList = GeneralUtility::trimExplode(LF, $submittedData['brofix']['email']);
            }
            foreach ($emailList as $emailAdd) {
                if (!GeneralUtility::validEmail($emailAdd)) {
                    $isValid = false;
                    $this->addMessage(
                        $lang->sL($this->languageFile . ':tasks.validate.invalidEmail'),
                        FlashMessage::ERROR
                    );
                }
            }
        }

        $row = BackendUtility::getRecord('pages', (int)$submittedData['brofix']['page'], '*', '', false);
        if (empty($row)) {
            $isValid = false;
            $this->addMessage(
                $lang->sL($this->languageFile . ':tasks.validate.invalidPage'),
                FlashMessage::ERROR
            );
        }
        if ($submittedData['brofix']['depth'] < 0) {
            $isValid = false;
            $this->addMessage(
                $lang->sL($this->languageFile . ':tasks.validate.invalidDepth'),
                FlashMessage::ERROR
            );
        }
        return $isValid;
    }

    /**
     * This method is used to save any additional input into the current task object
     * if the task class matches.
     *
     * @param array $submittedData Array containing the data submitted by the user
     * @param AbstractTask $task Reference to the current task object
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task)
    {
        /** @var ValidatorTask $task */
        $task->setDepth($submittedData['brofix']['depth']);
        $task->setPage($submittedData['brofix']['page']);
        $task->setEmail($submittedData['brofix']['email']);
        if ($submittedData['brofix']['emailOnBrokenLinkOnly']) {
            $task->setEmailOnBrokenLinkOnly(1);
        } else {
            $task->setEmailOnBrokenLinkOnly(0);
        }
        $task->setOverrideTsConfigString($submittedData['brofix']['configuration']);
        $task->setEmailTemplateFile($submittedData['brofix']['emailTemplateFile']);
    }

    /**
     * Get the title of the selected page
     *
     * @param int $pageId
     * @return string Page title or empty string
     */
    private function getPageTitle($pageId)
    {
        $page = BackendUtility::getRecord('pages', $pageId, 'title', '', false);
        if ($page === null) {
            return '';
        }
        return $page['title'];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
