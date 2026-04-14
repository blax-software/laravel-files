<?php

namespace Blax\Files\Enums;

enum FileLinkType: string
{
    // Visual Identity
    case Avatar = 'avatar';
    case ProfileImage = 'profile_image';
    case CoverImage = 'cover_image';
    case Banner = 'banner';
    case Background = 'background';
    case Logo = 'logo';
    case Icon = 'icon';
    case Thumbnail = 'thumbnail';

        // Documents
    case Document = 'document';
    case Invoice = 'invoice';
    case Contract = 'contract';
    case Certificate = 'certificate';
    case Report = 'report';

        // Media
    case Gallery = 'gallery';
    case Video = 'video';
    case Audio = 'audio';

        // Attachments
    case Attachment = 'attachment';
    case Download = 'download';

        // Catch-All
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Avatar       => 'Avatar',
            self::ProfileImage => 'Profile Image',
            self::CoverImage   => 'Cover Image',
            self::Banner       => 'Banner',
            self::Background   => 'Background',
            self::Logo         => 'Logo',
            self::Icon         => 'Icon',
            self::Thumbnail    => 'Thumbnail',
            self::Document     => 'Document',
            self::Invoice      => 'Invoice',
            self::Contract     => 'Contract',
            self::Certificate  => 'Certificate',
            self::Report       => 'Report',
            self::Gallery      => 'Gallery',
            self::Video        => 'Video',
            self::Audio        => 'Audio',
            self::Attachment   => 'Attachment',
            self::Download     => 'Download',
            self::Other        => 'Other',
        };
    }

    public function isImage(): bool
    {
        return in_array($this, [
            self::Avatar,
            self::ProfileImage,
            self::CoverImage,
            self::Banner,
            self::Background,
            self::Logo,
            self::Icon,
            self::Thumbnail,
            self::Gallery,
        ]);
    }
}
