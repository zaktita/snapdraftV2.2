import { LegalShell } from '@/components/marketing/legal-shell';

export default function Refund() {
    return (
        <LegalShell title="Refund Policy" lastUpdated="July 14, 2026">
            <p>
                Payments are processed by Lemon Squeezy as merchant of record. Subscriptions renew
                automatically until cancelled in the customer portal.
            </p>
            <h2>Cancellations</h2>
            <p>
                You can cancel anytime from the subscription portal. Access continues until the end
                of the paid period. Unused credits do not roll over unless stated on your plan.
            </p>
            <h2>Refunds</h2>
            <p>
                If you experience a billing error or a material service failure caused by us,
                contact contact@snapdraft.com within 14 days of the charge. Approved refunds are
                processed via Lemon Squeezy.
            </p>
            <h2>Invite / beta access</h2>
            <p>
                Beta invite credits are complimentary promotional access and are not refundable
                cash value.
            </p>
        </LegalShell>
    );
}
