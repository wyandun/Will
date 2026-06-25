/**
 * @vitest-environment jsdom
 *
 * Unit tests for ContractDetailPage.
 * Covers: rendering the loaded contract (title + status), the Timeline steps,
 * and write-gating of the Edit / Send-for-signing actions on a draft.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

const mockGet = vi.fn();
vi.mock('../../api/contracts', () => ({
  contractsApi: { get: (...a) => mockGet(...a), templates: vi.fn().mockResolvedValue([]) },
}));

let mockCanWrite = true;
vi.mock('../../hooks/usePermissions', () => ({
  usePermissions: () => ({ canWrite: () => mockCanWrite }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k, i18n: { language: 'en' } }),
}));

import ContractDetailPage from './ContractDetailPage';

const draft = {
  id: 1, title: 'Alpha agreement', status: 'draft', description: 'Body',
  draft_url: null, expires_at: null, signers: [],
  created_at: '2026-06-01T10:00:00Z', sent_at: null, signed_at: null,
  company: { id: 3, name: 'ACME', franchise: { id: 7, name: 'SM Florida' } },
  client: { id: 5, name: 'Dale Arepa', email: 'dale@sm.com' },
};

const renderDetail = () =>
  render(
    <MemoryRouter initialEntries={['/contracts/1']}>
      <Routes>
        <Route path="/contracts/:id" element={<ContractDetailPage />} />
      </Routes>
    </MemoryRouter>
  );

describe('ContractDetailPage', () => {
  beforeEach(() => {
    mockCanWrite = true;
    mockGet.mockReset().mockResolvedValue(draft);
  });

  it('renders the contract title and timeline steps', async () => {
    renderDetail();
    await waitFor(() => expect(screen.getByText('Alpha agreement')).toBeInTheDocument());
    expect(screen.getByText('contracts.detail.step_created')).toBeInTheDocument();
    expect(screen.getByText('contracts.detail.step_sent')).toBeInTheDocument();
    expect(screen.getByText('contracts.detail.step_signed')).toBeInTheDocument();
  });

  it('shows Edit and Send-for-signing on a draft when the user can write', async () => {
    renderDetail();
    await waitFor(() => expect(screen.getByText('Alpha agreement')).toBeInTheDocument());
    expect(screen.getByText('contracts.detail.edit')).toBeInTheDocument();
    expect(screen.getByText('contracts.detail.send_for_signing')).toBeInTheDocument();
  });

  it('hides write actions when the user is read-only', async () => {
    mockCanWrite = false;
    renderDetail();
    await waitFor(() => expect(screen.getByText('Alpha agreement')).toBeInTheDocument());
    expect(screen.queryByText('contracts.detail.edit')).not.toBeInTheDocument();
    expect(screen.queryByText('contracts.detail.send_for_signing')).not.toBeInTheDocument();
  });
});
