/**
 * @vitest-environment jsdom
 *
 * Unit tests for ProcessMapsPage (Business Process Management listing).
 *
 * Covers: header (title, total counter, "+ New map" write-gating), the
 * "All franchises" / "All clients" filter dropdowns with real-time counter
 * updates, client options scoped to the chosen franchise, loading / error /
 * empty states, opening the create modal, and the delete confirmation flow.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// ── Mocks (before component import) ─────────────────────────────────────────

const mockList = vi.fn();
const mockCreate = vi.fn();
const mockDelete = vi.fn();
vi.mock('../../api/processMaps', () => ({
  processMapsApi: {
    list: (...a) => mockList(...a),
    create: (...a) => mockCreate(...a),
    delete: (...a) => mockDelete(...a),
  },
}));

const mockGetFranchises = vi.fn();
vi.mock('../../api/franchises', () => ({
  franchisesApi: { getFranchises: (...a) => mockGetFranchises(...a) },
}));

const mockGetCompanies = vi.fn();
vi.mock('../../api/companies', () => ({
  companiesApi: { getCompanies: (...a) => mockGetCompanies(...a) },
}));

let mockCanWrite = true;
vi.mock('../../hooks/usePermissions', () => ({
  usePermissions: () => ({ canWrite: () => mockCanWrite }),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key, opts) => (opts?.count !== undefined ? `${opts.count} ${key}` : key),
    i18n: { language: 'en' },
  }),
}));

// Stub child components so the page test stays focused.
vi.mock('./ProcessMapCard', () => ({
  default: ({ map, onDelete }) => (
    <div data-testid="map-card">
      <span>{map.name_en}</span>
      <button onClick={() => onDelete(map)}>del-{map.id}</button>
    </div>
  ),
}));
vi.mock('./ProcessMapFormModal', () => ({
  default: ({ onClose }) => (
    <div data-testid="form-modal">
      <button onClick={onClose}>close</button>
    </div>
  ),
}));

import ProcessMapsPage from './ProcessMapsPage';

// ── Fixtures ────────────────────────────────────────────────────────────────

const franchises = [
  { id: 1, name: 'SM Florida' },
  { id: 2, name: 'SM Texas' },
];
const companies = [
  { id: 10, name: 'Acme', sm_franchise_id: 1 },
  { id: 11, name: 'Globex', sm_franchise_id: 2 },
];
const allMaps = [
  { id: 100, name_en: 'Map A', company_id: 10 },
  { id: 101, name_en: 'Map B', company_id: 11 },
];

beforeEach(() => {
  vi.clearAllMocks();
  mockCanWrite = true;
  mockGetFranchises.mockResolvedValue({ data: franchises });
  mockGetCompanies.mockResolvedValue({ data: companies });
  // Real-time filtering: a franchise filter narrows the result set.
  mockList.mockImplementation((params = {}) =>
    Promise.resolve({
      data: params.franchise_id
        ? allMaps.filter((m) => m.company_id === 10)
        : allMaps,
    })
  );
});

describe('ProcessMapsPage', () => {
  describe('Header', () => {
    it('renders the BPM title', async () => {
      render(<ProcessMapsPage />);
      expect(await screen.findByText('processMaps.title')).toBeInTheDocument();
    });

    it('renders the total maps counter', async () => {
      render(<ProcessMapsPage />);
      await waitFor(() =>
        expect(screen.getAllByText('2 processMaps.subtitle_other').length).toBeGreaterThan(0)
      );
    });

    it('renders "+ New map" when the user can write', async () => {
      render(<ProcessMapsPage />);
      expect(await screen.findByText('processMaps.new_map')).toBeInTheDocument();
    });

    it('hides "+ New map" when the user cannot write', async () => {
      mockCanWrite = false;
      render(<ProcessMapsPage />);
      await screen.findAllByTestId('map-card');
      expect(screen.queryByText('processMaps.new_map')).not.toBeInTheDocument();
    });
  });

  describe('Filters', () => {
    it('renders the franchise and client dropdowns', async () => {
      render(<ProcessMapsPage />);
      expect(await screen.findByText('processMaps.all_franchises')).toBeInTheDocument();
      expect(screen.getByText('processMaps.all_clients')).toBeInTheDocument();
    });

    it('filters by franchise and updates the counter in real time', async () => {
      render(<ProcessMapsPage />);
      await screen.findAllByTestId('map-card');
      expect(screen.getAllByTestId('map-card')).toHaveLength(2);

      const franchiseSelect = screen.getAllByRole('combobox')[0];
      fireEvent.change(franchiseSelect, { target: { value: '1' } });

      await waitFor(() =>
        expect(mockList).toHaveBeenCalledWith(expect.objectContaining({ franchise_id: '1' }))
      );
      await waitFor(() => expect(screen.getAllByTestId('map-card')).toHaveLength(1));
      expect(screen.getAllByText('1 processMaps.subtitle_one').length).toBeGreaterThan(0);
    });

    it('scopes the client dropdown to the selected franchise', async () => {
      render(<ProcessMapsPage />);
      await screen.findAllByTestId('map-card');

      // Before selecting a franchise both companies are available.
      expect(screen.getByText('Acme')).toBeInTheDocument();
      expect(screen.getByText('Globex')).toBeInTheDocument();

      const franchiseSelect = screen.getAllByRole('combobox')[0];
      fireEvent.change(franchiseSelect, { target: { value: '1' } });

      // Only the franchise-1 company remains in the client dropdown.
      await waitFor(() => expect(screen.queryByText('Globex')).not.toBeInTheDocument());
      expect(screen.getByText('Acme')).toBeInTheDocument();
    });
  });

  describe('States', () => {
    it('shows the loading indicator while fetching', () => {
      mockList.mockReturnValue(new Promise(() => {}));
      render(<ProcessMapsPage />);
      expect(screen.getByText('common.loading')).toBeInTheDocument();
    });

    it('shows the error banner on fetch failure', async () => {
      mockList.mockRejectedValue(new Error('boom'));
      render(<ProcessMapsPage />);
      expect(await screen.findByText('processMaps.load_error')).toBeInTheDocument();
    });

    it('shows the empty state when there are no maps', async () => {
      mockList.mockResolvedValue({ data: [] });
      render(<ProcessMapsPage />);
      expect(await screen.findByText('processMaps.empty_title')).toBeInTheDocument();
    });
  });

  describe('Create & delete', () => {
    it('opens the create modal from "+ New map"', async () => {
      const user = userEvent.setup();
      render(<ProcessMapsPage />);

      await user.click(await screen.findByText('processMaps.new_map'));
      expect(screen.getByTestId('form-modal')).toBeInTheDocument();
    });

    it('calls the delete API after confirming', async () => {
      const user = userEvent.setup();
      mockDelete.mockResolvedValue({});
      render(<ProcessMapsPage />);

      await screen.findAllByTestId('map-card');
      await user.click(screen.getByText('del-100')); // open confirm dialog
      await user.click(screen.getByText('processMaps.delete_btn')); // confirm

      await waitFor(() => expect(mockDelete).toHaveBeenCalledWith(100));
    });
  });
});
