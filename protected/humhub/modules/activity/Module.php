<?php

namespace humhub\modules\activity;

use Yii;
use humhub\models\Setting;
use humhub\modules\user\models\User;
use humhub\commands\CronController;
use humhub\modules\activity\components\BaseActivity;

/**
 * ActivityModule is responsible for all activities functions.
 *
 * @author Lucas Bartholemy <lucas@bartholemy.com>
 * @package humhub.modules_core.activity
 * @since 0.5
 */
class Module extends \yii\base\Module
{

    public function getMailUpdate(User $user, $interval)
    {
        $output = "";

        $receive_email_activities = $user->getSetting("receive_email_activities", 'core', Setting::Get('receive_email_activities', 'mailing'));

        // User never wants activity content
        if ($receive_email_activities == User::RECEIVE_EMAIL_NEVER) {
            return "";
        }

        // We are in hourly mode and user wants receive a daily summary
        if ($interval == CronController::EVENT_ON_HOURLY_RUN && $receive_email_activities == User::RECEIVE_EMAIL_DAILY_SUMMARY) {
            return "";
        }

        // We are in daily mode and user wants receive not daily
        if ($interval == CronController::EVENT_ON_DAILY_RUN && $receive_email_activities != User::RECEIVE_EMAIL_DAILY_SUMMARY) {
            return "";
        }

        // User is online and want only receive when offline
        if ($interval == CronController::EVENT_ON_HOURLY_RUN) {
            $isOnline = (count($user->httpSessions) > 0);
            if ($receive_email_activities == User::RECEIVE_EMAIL_WHEN_OFFLINE && $isOnline) {
                return "";
            }
        }

        $lastMailDate = $user->last_activity_email;
        if ($lastMailDate == "" || $lastMailDate == "0000-00-00 00:00:00") {
            $lastMailDate = new \yii\db\Expression('NOW() - INTERVAL 24 HOUR');
        }

        $stream = new \humhub\modules\dashboard\components\actions\DashboardStream('stream', Yii::$app->controller);
        $stream->limit = 50;
        $stream->mode = \humhub\modules\content\components\actions\Stream::MODE_ACTIVITY;
        $stream->user = $user;
        $stream->init();
        $stream->activeQuery->andWhere(['>', 'content.created_at', $lastMailDate]);

        foreach ($stream->getWallEntries() as $wallEntry) {
            $activity = $wallEntry->content->getPolymorphicRelation();
            $output .= $activity->getActivityBaseClass()->render(BaseActivity::OUTPUT_MAIL);
        }

        $user->last_activity_email = new \yii\db\Expression('NOW()');
        $user->save();

        return $output;
    }

}