<?php
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var \humhub\modules\user_recommender\models\SettingsForm $model */

$this->title = 'User Recommender Konfiguration';
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Konfiguration</strong> – Nutzervorschläge
    </div>

    <div class="panel-body">

        <?php $form = ActiveForm::begin([
            'action' => Url::to(['save']),
            'method' => 'post',
        ]); ?>

        <!-- SECTION 0: Allgemeine Einstellungen -->
        <fieldset>
            <legend>Allgemeine Einstellungen</legend>

            <?= $form->field($model, 'recommendationDisplayCount')
                ->input('number', ['min' => 1])
                ->hint('Anzahl der Empfehlungen, die pro Nutzer tatsächlich im Widget angezeigt werden.') ?>

            <?= $form->field($model, 'recommendationGenerateCount')
                ->input('number', ['min' => 1])
                ->hint('Gesamtzahl der Empfehlungen, die im Hintergrund berechnet und gespeichert werden.') ?>

            <?= $form->field($model, 'rejectCooldownRuns')
                ->input('number', ['min' => 0])
                ->hint('Anzahl der Runs, für die ein abgelehnter Nutzer nicht erneut vorgeschlagen wird.') ?>
        </fieldset>

        <br>

        <!-- SECTION 2: Variation der Empfehlungen -->
        <fieldset>
            <legend>Variation der Empfehlungen</legend>

            <p style="margin-bottom:1em;">
                Um für Abwechslung bei gleichbleibenden Bedingungen zu sorgen, werden durch die Multiplikatoren etwas mehr Elemente generiert. Von diesen werden dann zufällig so viele gezogen wie ursprünglich gewollt.
            </p>
            
            <?= $form->field($model, 'recommendationMultiplier')
                            ->input('number', ['step' => '0.1', 'min' => 1.0])
                            ->hint('Multiplikator für echte Empfehlungen.') ?>

            <?= $form->field($model, 'fallbackMultiplier')
                ->input('number', ['step' => '0.1', 'min' => 1.0])
                ->hint('Multiplikator für Fallbacks.') ?>

            
        </fieldset>


        <br>

        <!-- SECTION 0.5: Fallback-Optionen -->
        <fieldset>
            <legend>Fallback-Optionen</legend>

            <?= $form->field($model, 'fallbackTitleThreshold')
                ->input('number', ['min' => 0])
                ->hint('Ab wie vielen Fallback-Vorschlägen in allen generierten Vorschlägen der Widget-Titel von "Ähnliche Nutzer" zu "Interessante Nutzer" wechselt.') ?>
        </fieldset>

        <br>

        <!-- SECTION 1: Automatische Neuberechnung -->
        <fieldset>
            <legend>Automatische Neuberechnung</legend>

            <?= $form->field($model, 'regenerationDays')
                ->checkboxList($model::getWeekDays(), [
                    'itemOptions' => ['labelOptions' => ['style' => 'display:block;']]
                ])
                ->hint('An welchen Wochentagen sollen die Empfehlungen automatisch neu berechnet werden.') ?>

            <?= $form->field($model, 'regenerationTime')
                ->input('time')
                ->hint('Uhrzeit (HH:MM), zu der die automatische Neuberechnung ausgeführt wird. Geneuer Zeitpunkt kann um max. 1h abweichen, da das interne stündliche Cron-Event verwendet wird.') ?>

            <?= $form->field($model, 'lookbackDays')
                ->input('number', ['min' => 1])
                ->hint('Anzahl der Tage, die für die Empfehlungserstellung berücksichtigt werden sollen (mindestens 1).') ?>
        </fieldset>



        <br>

        <!-- SECTION 2: Empfehlungskriterien -->
        <fieldset>
            <legend>Empfehlungskriterien</legend>

            <?= $form->field($model, 'similarityThreshold')
                ->input('number', ['step' => '0.01', 'min' => 0, 'max' => 1])
                ->hint('Minimaler Ähnlichkeitswert (0–1), ab dem Nutzer überhaupt als ähnlich betrachtet werden. Je größer der Wert desto ähnlicher müssen sich Nutzer sein, um angezeigt zu werden..') ?>

            <?= $form->field($model, 'unfollowedPriorityCount')
                ->input('number', ['min' => 0])
                ->hint('Anzahl der Empfehlungen, die bevorzugt ungefolgte Personen zeigen. Muss kleiner oder gleich der Gesamtzahl der generierten Empfehlungen sein.') ?>

                <?= $form->field($model, 'unfollowedSimilarityThreshold')
                    ->input('number', ['step' => '0.01', 'min' => 0, 'max' => 1])
                    ->hint('Minimaler Ähnlichkeitswert (0–1) für nicht gefolgte Nutzer, mit denen noch aufgefüllt wird um die Anzahl ungefolgter Personen zu erreichen.') ?>

        </fieldset>

        <br>

        <!-- SECTION 3: Optionen zur Darstellung -->
        <fieldset>
            <legend>Darstellungsoptionen</legend>

            <?= $form->field($model, 'highlightMutual')
                ->checkbox()
                ->hint('Falls aktiviert, werden Nutzer hervorgehoben, die sich gegenseitig besonders ähnlich sind.') ?>

            <?= $form->field($model, 'highlightMutualThresh')
                ->input('number', ['step' => '0.01', 'min' => 0, 'max' => 1])
                ->hint('Erst ab diesem Ähnlichkeitswert werden existierende Empfehlungen, die gegenseitig sind, hervorgehoben.') ?>


            <?= $form->field($model, 'shuffleRecommendations')
                ->checkbox()
                ->hint('Falls aktiviert, werden Empfehlungen zufällig durchmischt. Standard ist eine Sortierung nach Score. Nicht-Fallback Vorschläge sind immer vor Fallbacks') ?>
        </fieldset>

        <!-- SECTION 4: Umfrage-Info Symbol -->
        <fieldset>
            <legend>Survey Modal</legend>

            <?= $form->field($model, 'showSurveyInfo')
                ->checkbox()
                ->hint('Aktiviere diese Option, um oben rechts im Widget ein Info-Symbol für eine Umfrage o.ö. anzuzeigen.') ?>

            <?= $form->field($model, 'surveyTitle')
                ->textInput(['maxlength' => true])
                ->hint('Überschrift des Modals.') ?>

            <?= $form->field($model, 'surveyText')
                ->textarea(['rows' => 4])
                ->hint('Text, der im Modal angezeigt wird.') ?>

            <?= $form->field($model, 'surveyButtonText')
                ->textInput(['maxlength' => true])
                ->hint('Text für den Button, der auf die Umfrage verlinkt.') ?>

            <?= $form->field($model, 'surveyButtonLink')
                ->textInput(['maxlength' => true])
                ->hint('Link zur Umfrage (muss eine gültige URL sein).') ?>
        </fieldset>


        <br>

        <div class="form-group">
            <?= Html::submitButton('Speichern', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>

        <!-- Button zum Generieren der Empfehlungen für alle Benutzer -->
        <div class="form-group">
            <?= Html::a('Generiere Empfehlungen für alle Benutzer', ['generate-recommendations'], [
                'class' => 'btn btn-success',
                'data' => [
                    'confirm' => 'Möchten Sie wirklich alle Empfehlungen generieren?',
                    'method' => 'post',
                ]
            ]) ?>
        </div>
    </div>
</div>
