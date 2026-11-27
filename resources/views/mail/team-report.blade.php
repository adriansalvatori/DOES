{{-- Body of the emailed team report; the PDF is attached. --}}
<div style="font-family: Arial, sans-serif; font-size: 14px; color: #1c1917;">
    <p>{{ __('Attached is the :title for :period.', ['title' => mb_strtolower($period->title()), 'period' => $period->label()]) }}</p>
    <p style="color: #78716c;">{{ __('It covers points approved, proofs, rework and open work per designer. You can also download any period from Analytics.') }}</p>
</div>
