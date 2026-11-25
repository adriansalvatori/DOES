{{-- Translated strings used by resources/js (read via window.kudosT). Keys are the English source text. --}}
@php
    $kudosI18n = [
        'Discard unsaved changes?' => __('Discard unsaved changes?'),
        'You have edited information or changes that are not saved. If you leave now, these changes will be lost.' => __('You have edited information or changes that are not saved. If you leave now, these changes will be lost.'),
        'You have unsaved edited information. If you leave now, the modified data will be lost.' => __('You have unsaved edited information. If you leave now, the modified data will be lost.'),
        'Yes, discard changes' => __('Yes, discard changes'),
        'Keep editing' => __('Keep editing'),
        'You have unsaved changes.' => __('You have unsaved changes.'),
        'WO #:number copied to clipboard' => __('WO #:number copied to clipboard'),
        'System notifications and sound enabled successfully!' => __('System notifications and sound enabled successfully!'),
        'CLIENT' => __('CLIENT'),
        'Check the status of your' => __('Check the status of your'),
        'orders online.' => __('orders online.'),
        'Scan to access' => __('Scan to access'),
        'your orders' => __('your orders'),
        'The QR card could not be generated as JPG. Please try again.' => __('The QR card could not be generated as JPG. Please try again.'),
    ];
@endphp
<script>
    window.KudosI18n = @json($kudosI18n);
</script>
