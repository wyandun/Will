/**
 * @vitest-environment jsdom
 *
 * Unit tests for ContractsPage (contracts listing).
 * Covers: header title + count, write-gated "+ New contract" button and
 * view-only banner, row rendering, and the empty state.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

const mockList = vi.fn();
const mockRemove = vi.fn();
vi.mock('../../api/contracts', () => ({
  contractsApi: { list: (...a) => mockList(...a), remove: (...a) => mockRemove(...a) },
}));

let mockCanWrite = true;
vi.mock('../../hooks/usePermissions', () => ({
  usePermissions: () => ({ canWrite: () => mockCanWrite }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key, opts) => (opts ? `${key}` : key),
    i18n: { language: 'en' },
  }),
}));

// Keep the page test focused — stub the create modal.
vi.mock('./ContractFormModal', () => ({ default: () => null }));

import ContractsPage from './ContractsPage';

const renderPage = () => render(<MemoryRouter><ContractsPage /></MemoryRouter>);

const contracts = [
  {
    id: 1, title: 'Alpha agreement', status: 'draft',
    description: 'First', company: { name: 'ACME', franchise: { id: 7, name: 'SM Florida' } },
    client: { name: 'Dale Arepa', email: 'dale@sm.com' },
  },
];

describe('ContractsPage', () => {
  beforeEach(() => {
    mockCanWrite = true;
    mockList.mockReset().mockResolvedValue({ data: contracts, meta: { total: 1 } });
  });

  it('renders the title and a contract row', async () => {
    renderPage();
    expect(screen.getByText('contracts.title')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText('Alpha agreement')).toBeInTheDocument());
  });

  it('shows the new-contract button when the user can write', async () => {
    renderPage();
    await waitFor(() => expect(screen.getByText('Alpha agreement')).toBeInTheDocument());
    expect(screen.getByText('contracts.new_contract')).toBeInTheDocument();
    expect(screen.queryByText('contracts.view_only')).not.toBeInTheDocument();
  });

  it('hides the new-contract button and shows the view-only banner when read-only', async () => {
    mockCanWrite = false;
    renderPage();
    await waitFor(() => expect(screen.getByText('Alpha agreement')).toBeInTheDocument());
    expect(screen.queryByText('contracts.new_contract')).not.toBeInTheDocument();
    expect(screen.getByText('contracts.view_only')).toBeInTheDocument();
  });

  it('renders the empty state when there are no contracts', async () => {
    mockList.mockResolvedValue({ data: [], meta: { total: 0 } });
    renderPage();
    await waitFor(() => expect(screen.getByText('contracts.empty_title')).toBeInTheDocument());
  });
});
