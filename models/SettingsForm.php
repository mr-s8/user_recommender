<?php

namespace humhub\modules\user_recommender\models;

use Yii;
use yii\base\Model;

class SettingsForm extends Model
{
    public $recommendationDisplayCount;  
    public $recommendationGenerateCount; 

    public $regenerationDays = [];       
    public $regenerationTime;            
    public $similarityThreshold;         
    public $unfollowedPriorityCount;     
    public $highlightMutual;             
    public $highlightMutualThresh;
    public $shuffleRecommendations;      
    public $rejectCooldownRuns;          
    public $fallbackTitleThreshold;      
    public $lookbackDays; 
    public $fallbackMultiplier;          
    public $recommendationMultiplier;    
    public $unfollowedSimilarityThreshold;
    public $showSurveyInfo = true;       
    public $surveyTitle = 'Umfrage';    
    public $surveyText = 'Dieses Empfehlungssystem für Nutzer wird im Rahmen einer Bachelorarbeit entwickelt. Um die Wirkung besser einschätzen und das System gezielt verbessern zu können, würden wir uns sehr über Ihre Teilnahme an einer kurzen Umfrage freuen.'; 
    public $surveyButtonText = 'Zur Umfrage'; 
    public $surveyButtonLink = 'https://example.com/survey'; 



    public function rules()
    {
       return [
            [['recommendationDisplayCount', 'recommendationGenerateCount'], 'integer', 'min' => 1],
            [['unfollowedPriorityCount', 'rejectCooldownRuns', 'fallbackTitleThreshold'], 'integer', 'min' => 0],
            [['lookbackDays'], 'integer', 'min' => 1],
            [['similarityThreshold', 'unfollowedSimilarityThreshold', 'highlightMutualThresh'], 'number', 'min' => 0, 'max' => 1], 
            [['regenerationTime'], 'match', 'pattern' => '/^(?:[01]\d|2[0-3]):[0-5]\d$/', 'message' => 'Bitte eine gültige Uhrzeit im Format HH:MM angeben.'],
            [['highlightMutual', 'shuffleRecommendations', 'showSurveyInfo'], 'boolean'], 
            ['unfollowedPriorityCount', 'compare', 'compareAttribute' => 'recommendationGenerateCount', 'operator' => '<=', 'message' => 'Darf nicht größer als die Anzahl der zu generierenden Empfehlungen sein.'],
            [['regenerationDays'], 'each', 'rule' => ['in', 'range' => ['mon','tue','wed','thu','fri','sat','sun']]],
            [['fallbackMultiplier', 'recommendationMultiplier'], 'number', 'min' => 1.0],
            [['surveyTitle', 'surveyText', 'surveyButtonText', 'surveyButtonLink'], 'string'],
            [['surveyButtonLink'], 'url'],
        ];

    }

    public function loadSettings()
    {
        $settings = Yii::$app->getModule('user_recommender')->settings;

        $this->recommendationDisplayCount  = (int) $settings->get('recommendationCountToShow', 5);
        $this->recommendationGenerateCount = (int) $settings->get('recommendationCountToGenerate', 10);
        $this->regenerationDays = json_decode($settings->get('regenerationDays', '["mon","wed","fri"]'), true);
        $this->regenerationTime            = $settings->get('regenerationTime', '03:00');
        $this->similarityThreshold         = (float) $settings->get('similarityThreshold', 0.0);
        $this->unfollowedPriorityCount     = (int) $settings->get('unfollowedPriorityCount', 0);
        $this->highlightMutual             = (bool) $settings->get('highlightMutual', false);
        $this->shuffleRecommendations      = (bool) $settings->get('shuffleRecommendations', false);
        $this->rejectCooldownRuns          = (int) $settings->get('rejectCooldownRuns', 3);
        $this->fallbackTitleThreshold      = (int) $settings->get('fallbackTitleThreshold', $this->recommendationGenerateCount);
        $this->lookbackDays                = (int) $settings->get('lookbackDays', 7);
        $this->fallbackMultiplier          = (float) $settings->get('fallbackMultiplier', 1.0);
        $this->recommendationMultiplier    = (float) $settings->get('recommendationMultiplier', 1.0);
        $this->unfollowedSimilarityThreshold   = (float) $settings->get('unfollowedSimilarityThreshold', 0.0);
        $this->showSurveyInfo = (bool) $settings->get('showSurveyInfo', false);
        $this->surveyTitle = $settings->get('surveyTitle', 'Umfrage');
        $this->surveyText = $settings->get('surveyText', 'Dieses Empfehlungssystem für Nutzer wird im Rahmen einer Bachelorarbeit entwickelt. Um die Wirkung besser einschätzen und das System gezielt verbessern zu können, würden wir uns sehr über Ihre Teilnahme an einer kurzen Umfrage freuen.');
        $this->surveyButtonText = $settings->get('surveyButtonText', 'Zur Umfrage');
        $this->surveyButtonLink = $settings->get('surveyButtonLink', 'https://example.com/survey');
        $this->highlightMutualThresh = (float) $settings->get('highlightMutualThresh', 0.0);

    }

    public function saveSettings()
    {
        $settings = Yii::$app->getModule('user_recommender')->settings;

        $settings->set('recommendationCountToShow', $this->recommendationDisplayCount);
        $settings->set('recommendationCountToGenerate', $this->recommendationGenerateCount);
        $settings->set('regenerationDays', json_encode($this->regenerationDays));
        $settings->set('regenerationTime', $this->regenerationTime);
        $settings->set('similarityThreshold', $this->similarityThreshold);
        $settings->set('unfollowedPriorityCount', $this->unfollowedPriorityCount);
        $settings->set('highlightMutual', $this->highlightMutual);
        $settings->set('shuffleRecommendations', $this->shuffleRecommendations);
        $settings->set('rejectCooldownRuns', $this->rejectCooldownRuns);
        $settings->set('fallbackTitleThreshold', $this->fallbackTitleThreshold);
        $settings->set('lookbackDays', $this->lookbackDays);
        $settings->set('fallbackMultiplier', $this->fallbackMultiplier);
        $settings->set('recommendationMultiplier', $this->recommendationMultiplier);
        $settings->set('unfollowedSimilarityThreshold', $this->unfollowedSimilarityThreshold);
        $settings->set('showSurveyInfo', $this->showSurveyInfo);
        $settings->set('surveyTitle', $this->surveyTitle);
        $settings->set('surveyText', $this->surveyText);
        $settings->set('surveyButtonText', $this->surveyButtonText);
        $settings->set('surveyButtonLink', $this->surveyButtonLink);
        $settings->set('highlightMutualThresh', $this->highlightMutualThresh);

    }

    public function attributeLabels()
{
    return [
        'recommendationDisplayCount'       => 'Anzahl anzuzeigender Empfehlungen',
        'recommendationGenerateCount'      => 'Anzahl zu generierender Empfehlungen',
        'regenerationDays'                 => 'Regenerationstage',
        'regenerationTime'                 => 'Regenerationszeit',
        'similarityThreshold'              => 'Ähnlichkeitsschwelle',
        'unfollowedPriorityCount'          => 'Anzahl bevorzugt ungefolgter Empfehlungen',
        'highlightMutual'                  => 'Gegenseitige Vorschläge hervorheben',
        'shuffleRecommendations'           => 'Empfehlungen mischen',
        'rejectCooldownRuns'               => 'Cooldown für abgelehnte Empfehlungen (Runs)',
        'fallbackTitleThreshold'           => 'Schwelle für Fallback-Titel',
        'lookbackDays'                     => 'Blick zurück in Tagen',
        'fallbackMultiplier'               => 'Fallback-Multiplikator',
        'recommendationMultiplier'         => 'Empfehlungs-Multiplikator',
        'unfollowedSimilarityThreshold'    => 'Ähnlichkeitsschwelle für ungefolgte Nutzer',
        'showSurveyInfo'                   => 'Umfrage Option',
        'surveyTitle'                      => 'Umfragetitel',
        'surveyText'                       => 'Umfragetext',
        'surveyButtonText'                 => 'Text des Umfrage-Buttons',
        'surveyButtonLink'                 => 'Link des Umfrage-Buttons',
        'highlightMutualThresh'            => 'Schwelle Gegensetiges Hervorheben'
    ];
}

    public static function getWeekDays()
    {
        return [
            'mon' => 'Montag',
            'tue' => 'Dienstag',
            'wed' => 'Mittwoch',
            'thu' => 'Donnerstag',
            'fri' => 'Freitag',
            'sat' => 'Samstag',
            'sun' => 'Sonntag',
        ];
    }
}
