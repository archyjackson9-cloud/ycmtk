{{-- Proof-of-payment preview shown in the Payments "View proof" modal. --}}
<div class="cy-proof">
    <dl class="cy-proof-facts">
        <div>
            <dt>Expected amount</dt>
            <dd>GHS {{ number_format((float) $payment->amount, 2) }}</dd>
        </div>
        <div>
            <dt>Reference</dt>
            <dd>{{ $payment->reference }}</dd>
        </div>
        @if ($payment->payer_phone)
            <div>
                <dt>Payer phone</dt>
                <dd><a href="tel:{{ $payment->payer_phone }}">{{ $payment->payer_phone }}</a></dd>
            </div>
        @endif
    </dl>

    @if ($isImage)
        <a href="{{ $url }}" target="_blank" rel="noopener" title="Open full size">
            <img src="{{ $url }}" alt="Payment proof uploaded by the customer" class="cy-proof-img" loading="lazy">
        </a>
    @else
        <p class="cy-note">This proof is a document, not a photo.</p>
    @endif

    <p class="cy-proof-open"><a href="{{ $url }}" target="_blank" rel="noopener">Open the file in a new tab</a></p>
</div>
