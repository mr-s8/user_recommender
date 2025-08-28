<?php

namespace humhub\modules\user_recommender\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\user\models\ProfileField;
use humhub\modules\user_recommender\models\SettingsForm;
use humhub\modules\user_recommender\services\RecommendationService;
use Yii;

class AdminController extends Controller
{


    public function actionIndex()
    {
        $form = new SettingsForm();
        $form->loadSettings();
        $fields = ProfileField::find()->all();

        return $this->render('index', [
            'fields' => $fields,
            'model' => $form,
        ]);
    }

    public function actionSave()
    {
        $form = new SettingsForm();
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $form->saveSettings();
            Yii::$app->session->setFlash('success', 'Einstellungen gespeichert.');
        } else {
            Yii::$app->session->setFlash('error', 'Fehler beim Speichern der Einstellungen.');
        }

        return $this->redirect(['index']);
    }

    public function actionGenerateRecommendations()
    {
        RecommendationService::generateRecommendationsForAllUsers();
        Yii::$app->session->setFlash('success', 'Empfehlungen wurden erfolgreich generiert!');
        return $this->redirect(['index']);
    }


}
