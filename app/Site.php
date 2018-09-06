<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Site extends Model {

    protected $table = 'site';

    protected $fillable = [
        'name',
        'email',
        'url',
        'smtpHost',
        'smtpPort',
        'smtpUser',
        'smtpPassword',
        'smtpEncryption',
        'imapHost',
        'imapPort',
        'imapUser',
        'imapPassword',
        'imapEncryption',
        'dateFormat',
        'isConnect',
        'token',
    ];

    public static function getCMSSites() {
        $data = Site::select("*")
            ->where("type", "=", "cms")
            ->get();
        return $data;
    }
}
