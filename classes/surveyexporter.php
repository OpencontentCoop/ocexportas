<?php

class SurveyExporter
{
    private $contentObjectID;

    private $contentClassAttributeID;

    private $languageCode;

    /**
     * @var eZSurvey
     */
    private $survey;

    /**
     * @var eZINI
     */
    private $surveyINI;

    /**
     * @var eZSurveyQuestion[]
     */
    private $questionList;

    private $answerList;

    private $indexList;

    private $options = array();

    public function __construct($contentObjectID, $contentClassAttributeID, $languageCode)
    {
        $this->contentObjectID = (int)$contentObjectID;
        $this->contentClassAttributeID = (int)$contentClassAttributeID;
        $this->languageCode = $languageCode;

        $this->survey = eZSurvey::fetchByObjectInfo($this->contentObjectID, $this->contentClassAttributeID,
            $this->languageCode);
        $this->surveyINI = eZINI::instance('ezsurvey.ini');

        /** @var eZSurveyQuestion[] $questionList */
        $this->questionList = $this->survey instanceof eZSurvey ? $this->survey->fetchQuestionList() : array();

        $this->options = eZINI::instance('exportas.ini')->group('Settings');
    }

    private function getIndexList()
    {
        if ($this->indexList === null) {
            $this->indexList = array();
            foreach (array_keys($this->questionList) as $key) {
                if ($this->questionList[$key]->canAnswer()) {
                    $oldKey = $this->questionList[$key]->attribute('original_id');
                    $this->indexList[$oldKey] = array();
                }
            }

            $showHeadlineUserName = $this->surveyINI->variable('CSVExportSettings',
                'ShowUserName') == 'true' ? true : false;
            if ($showHeadlineUserName === true) {
                $this->indexList['user_id'] = array('columns_count' => 1);
            }
        }

        return $this->indexList;
    }

    private function setIndexColumnCount($index, $count)
    {
        $this->getIndexList();

        if (!isset( $this->indexList[$index] )) {
            $this->indexList[$index] = array();
        }

        if (!isset( $this->indexList[$index]['columns_count'] )) {
            $this->indexList[$index]['columns_count'] = $count;
        } elseif ($count > $this->indexList[$index]['columns_count']) {
            $this->indexList[$index]['columns_count'] = $count;
        }
    }

    private function getQuestionList()
    {
        $questions = array();

        foreach (array_keys($this->questionList) as $key) {
            if ($this->questionList[$key]->canAnswer()) {
                $oldKey = $this->questionList[$key]->attribute('original_id');
                $questions[$oldKey] = $this->questionList[$key]->attribute('text');
            }
        }

        $showHeadlineUserName = $this->surveyINI->variable('CSVExportSettings',
            'ShowUserName') == 'true' ? true : false;
        if ($showHeadlineUserName === true) {
            $questions['user_id'] = $this->surveyINI->variable('CSVExportSettings', 'HeadlineUserName');
        }

        return $questions;
    }

    private function getAnswerList()
    {
        if ($this->answerList === null) {
            $db = eZDB::instance();
            $query = "SELECT ezsurveyquestionresult.result_id as result_id, question_id, questionoriginal_id, text, ezsurveyresult.user_id as user_id
                                  FROM ezsurveyquestionresult, ezsurveyresult, ezsurvey
                                  WHERE ezsurveyresult.id=ezsurveyquestionresult.result_id AND
                                        ezsurveyresult.survey_id=ezsurvey.id AND
                                        contentclassattribute_id='" . $this->contentClassAttributeID . "' AND
                                        contentobject_id='" . $this->contentObjectID . "' AND
                                        language_code='" . $this->languageCode . "'
                                  ORDER BY tstamp ASC, ezsurveyquestionresult.result_id ASC";
            $rows = $db->arrayQuery($query);
            //return $rows;
            $extraQuery = "SELECT ezsurveyquestionmetadata.result_id as result_id, question_id, value
                                  FROM ezsurveyquestionmetadata, ezsurveyresult, ezsurvey
                                  WHERE ezsurveyresult.id=ezsurveyquestionmetadata.result_id AND
                                        ezsurveyresult.survey_id=ezsurvey.id AND
                                        ezsurveyquestionmetadata.value<>'' AND
                                        contentclassattribute_id='" . $this->contentClassAttributeID . "' AND
                                        contentobject_id='" . $this->contentObjectID . "' AND
                                        language_code='" . $this->languageCode . "'
                                  ORDER BY tstamp ASC, ezsurveyquestionmetadata.result_id ASC";

            $extraResultArray = $db->arrayQuery($extraQuery);

            $extraResultHash = array();
            foreach ($extraResultArray as $extraResultItem) {
                $extraResultHash[$extraResultItem['result_id']][$extraResultItem['question_id']] = $extraResultItem['value'];
            }

            $data = array();
            $showHeadlineUserName = $this->surveyINI->variable('CSVExportSettings',
                'ShowUserName') == 'true' ? true : false;

            foreach ($rows as $row) {
                if (!isset( $data[$row['result_id']] )) {
                    $data[$row['result_id']] = array();
                }

                if (isset( $data[$row['result_id']][$row['questionoriginal_id']] )) {
                    $data[$row['result_id']][$row['questionoriginal_id']][] = $row['text'];
                }  // esp. for multiple check boxes
                else {
                    $data[$row['result_id']][$row['questionoriginal_id']] = $this->parseText($row['text']);
                }

                if (isset( $extraResultHash[$row['result_id']][$row['question_id']] )) {
                    $data[$row['result_id']][$row['questionoriginal_id']][] = $extraResultHash[$row['result_id']][$row['question_id']];
                    unset( $extraResultHash[$row['result_id']][$row['question_id']] );
                }

                if (!isset( $data[$row['result_id']]['user_id'] ) and $showHeadlineUserName === true) {
                    $string = $row['user_id'];
                    $object = eZContentObject::fetch((int)$row['user_id']);
                    if ($object instanceof eZContentObject) {
                        $string = $object->attribute('name');
                    }
                    $data[$row['result_id']]['user_id'] = array($string);
                }

                $this->setIndexColumnCount($row['questionoriginal_id'],
                    count($data[$row['result_id']][$row['questionoriginal_id']]));
            }
            $this->answerList = $data;
        }

        return $this->answerList;

    }

    private function parseText($text)
    {
        $data = @unserialize($text);
        if ($text === 'b:0;' || $data !== false) {
            if (is_array($data)) {
                return $data;
            }
        }

        return array($text);
    }

    public function getExpandedQuestionList()
    {
        $this->getIndexList();
        $questions = $this->getQuestionList();
        $this->getAnswerList();

        $formattedQuestions = array(
            'ID' => 'ID'
        );

        foreach ($this->indexList as $index => $value) {
            if (isset($questions[$index])) {
                for ($i = 0; $i < $value["columns_count"]; $i++) {
                    $formattedQuestions[$index . '_' . $i] = $i == 0 && isset( $questions[$index] ) ? $questions[$index] : ' ';
                }
            }
        }

        return $formattedQuestions;
    }

    public function getExpandedAnswerList()
    {
        $this->getIndexList();
        $answers = $this->getAnswerList();
        $questions = $this->getQuestionList();

        $formattedAnswers = array();

        foreach ($answers as $resultId => $answer) {
            $formattedAnswer = array(
                'ID' => $resultId
            );
            foreach ($this->indexList as $index => $value) {
                if (isset($questions[$index])) {
                    for ($i = 0; $i < $value["columns_count"]; $i++) {
                        if (isset( $answer[$index][$i] )) {
                            $formattedAnswer[$index . '_' . $i] = $answer[$index][$i];
                        } else {
                            $formattedAnswer[$index . '_' . $i] = '';
                        }
                    }
                }
            }

            $formattedAnswers[] = $formattedAnswer;
        }

        return $formattedAnswers;
    }

    public function canExport()
    {
        if (!$this->survey instanceof eZSurvey || !$this->survey->published()) {
            return false;
        }

        return true;
    }

    public function handleRawResultsView()
    {
        echo '<pre>';
        print_r($this->getQuestionList());
        print_r($this->getAnswerList());
        eZExecution::cleanExit();
    }

    public function handleTableView()
    {
        //https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css
        echo '<html>';
        echo '<head>';
        echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">';
        echo '</head>';
        echo '<body>';
        echo '<table class="table table-bordered">';
        echo '<tr>';
        foreach($this->getExpandedQuestionList() as $question){
            echo '<th>' . $question . '</th>';
        }
        echo '</tr>';

        $answers = $this->getExpandedAnswerList();
        foreach($answers as $answer){
            echo '<tr>';
            foreach($answer as $text){
                echo '<td>' . $text . '</td>';
            }
            echo '</tr>';
            flush();
        }

        echo '</table>';
        echo '</body>';
        echo '</html>';

    }

    public function handleCsvDownload()
    {
        $filename = "survey_{$this->contentObjectID}_{$this->contentClassAttributeID}_{$this->languageCode}.csv";
        header('X-Powered-By: eZ Publish');
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        header("Pragma: no-cache");
        header("Expires: 0");


        $output = fopen('php://output', 'w');
        fputcsv($output,
            $this->getExpandedQuestionList()
        );

        $answers = $this->getExpandedAnswerList();
        foreach($answers as $answer){
            fputcsv($output,
                $answer
            );
            flush();
        }
    }

}
