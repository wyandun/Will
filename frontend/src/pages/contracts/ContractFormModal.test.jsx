/**
 * @vitest-environment jsdom
 *
 * Unit tests for ContractFormModal (new contract).
 * Covers: field rendering, that an empty title keeps the submit button
 * disabled, and that the client dropdown loads from the franchise members.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

const mockCreate = vi.fn();
vi.mock('../../api/contracts', () => ({
  contractsApi: { create: (...a) => mockCreate(...a) },
}));

const mockGetFranchises = vi.fn();
const mockGetMembers = vi.fn();
vi.mock('../../api/franchises', () => ({
  franchisesApi: {
    getFranchises: (...a) => mockGetFranchises(...a),
    getMembers: (...a) => mockGetMembers(...a),
  },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k) => k, i18n: { language: 'en' } }),
}));

import ContractFormModal from './ContractFormModal';

describe('ContractFormModal', () => {
  beforeEach(() => {
    // Single franchise → auto-selected, members loaded.
    mockGetFranchises.mockResolvedValue({ data: [{ id: 1, name: 'SM Florida' }] });
    mockGetMembers.mockResolvedValue({ clients: [{ id: 5, name: 'Dale Arepa', email: 'dale@sm.com' }] });
    mockCreate.mockReset();
  });

  it('renders the required fields', async () => {
    render(<ContractFormModal onClose={vi.fn()} onCreated={vi.fn()} />);
    expect(screen.getByText('contracts.modal.title')).toBeInTheDocument();
    expect(screen.getByText('contracts.modal.client_label')).toBeInTheDocument();
    expect(screen.getByText('contracts.modal.title_label')).toBeInTheDocument();
  });

  it('keeps the submit button disabled while the title is empty', async () => {
    render(<ContractFormModal onClose={vi.fn()} onCreated={vi.fn()} />);
    const submit = screen.getByText('contracts.modal.submit').closest('button');
    expect(submit).toBeDisabled();
  });

  it('loads clients from the selected franchise', async () => {
    render(<ContractFormModal onClose={vi.fn()} onCreated={vi.fn()} />);
    await waitFor(() => expect(screen.getByText(/Dale Arepa/)).toBeInTheDocument());
  });
});
