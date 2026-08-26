import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { InboxSenderClassificationCorrection } from '@/components/InboxSenderClassificationCorrection';

const apiMock = vi.fn();

vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => apiMock(...args),
}));

describe('InboxSenderClassificationCorrection', () => {
  it('persists a recruiter correction as a sender-level job alert rule', async () => {
    apiMock.mockResolvedValue({});
    const onSaved = vi.fn();

    render(
      <InboxSenderClassificationCorrection
        messageId={42}
        sender="Jobs <alerts@example.com>"
        category="RECRUITER_OPPORTUNITY"
        onSaved={onSaved}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Ce n’est pas un recruteur' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith(
      '/integrations/gmail/messages/42/sender-classification',
      expect.objectContaining({
        method: 'PUT',
        body: JSON.stringify({ category: 'JOB_ALERT' }),
      }),
    ));
    await waitFor(() => expect(onSaved).toHaveBeenCalledTimes(1));
    expect(screen.getByText(/réutilisée pour les prochains messages/)).toBeInTheDocument();
  });

  it('does not expose the correction on an already classified platform alert', () => {
    render(
      <InboxSenderClassificationCorrection
        messageId={42}
        sender="alerts@example.com"
        category="JOB_ALERT"
        onSaved={() => undefined}
      />,
    );

    expect(screen.queryByRole('button', { name: 'Ce n’est pas un recruteur' })).not.toBeInTheDocument();
  });
});
