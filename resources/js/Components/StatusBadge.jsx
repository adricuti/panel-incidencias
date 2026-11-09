import { STATUS_STYLES, findStatus } from '../lib/status';

export default function StatusBadge({ status, statuses }) {
    const label = findStatus(statuses, status)?.label ?? status;
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[status] ?? ''}`}>
            {label}
        </span>
    );
}
