/** Must match `config/beta_application.php` */
export const BETA_ROLE_OPTIONS: { value: string; label: string }[] = [
    { value: 'founder', label: 'Founder / solo builder' },
    { value: 'marketer', label: 'Marketer' },
    { value: 'agency', label: 'Agency' },
    { value: 'designer', label: 'Designer' },
    { value: 'content_creator', label: 'Content creator' },
    { value: 'other', label: 'Other' },
];

export const BETA_VOLUME_OPTIONS: { value: string; label: string }[] = [
    { value: '1-5', label: '1–5' },
    { value: '6-20', label: '6–20' },
    { value: '21-50', label: '21–50' },
    { value: '51-100', label: '51–100' },
    { value: '100-plus', label: '100+' },
];
