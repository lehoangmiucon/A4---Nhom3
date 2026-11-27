package com.apphelpdesk.apphelpdesk

import android.app.NotificationChannel
import android.app.NotificationManager
import android.media.AudioAttributes
import android.media.Ringtone
import android.media.RingtoneManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private var notificationRingtone: Ringtone? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)
            if (manager.getNotificationChannel(NOTIFICATION_CHANNEL_ID) == null) {
                val channel = NotificationChannel(
                    NOTIFICATION_CHANNEL_ID,
                    "Thông báo Helpdesk",
                    NotificationManager.IMPORTANCE_HIGH,
                ).apply {
                    description = "Âm báo phiếu mới và tin nhắn Helpdesk"
                    enableVibration(true)
                    setSound(
                        RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION),
                        AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                            .build(),
                    )
                }
                manager.createNotificationChannel(channel)
            }
        }
        super.onCreate(savedInstanceState)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, SOUND_CHANNEL)
            .setMethodCallHandler { call, result ->
                if (call.method != "play") {
                    result.notImplemented()
                    return@setMethodCallHandler
                }
                try {
                    notificationRingtone?.stop()
                    val sound: Uri? = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
                    if (sound == null) {
                        result.success(false)
                        return@setMethodCallHandler
                    }
                    notificationRingtone = RingtoneManager.getRingtone(applicationContext, sound)
                    notificationRingtone?.audioAttributes = AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build()
                    notificationRingtone?.play()
                    result.success(true)
                } catch (error: Exception) {
                    result.error("SOUND_ERROR", error.message, null)
                }
            }
    }

    companion object {
        private const val SOUND_CHANNEL = "com.apphelpdesk/notification_sound"
        private const val NOTIFICATION_CHANNEL_ID = "helpdesk_alerts_v2"
    }
}
