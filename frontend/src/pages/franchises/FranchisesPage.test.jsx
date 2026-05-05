/**
 * @vitest-environment jsdom
 *
 * Unit tests for the FranchisesPage component.
 *
 * Covers:
 *  - Header elements (title, counter, Back button, New franchise button)
 *  - Search bar filters cards by name in real time and shows result count
 *  - Franchise card renders: avatar with initials, name, country, status badge,
 *    email, admin/client counts
 *  - Card buttons: Edit, Deactivate/Activate, Delete
 *  - Inactive franchise: desaturated card, "Inactive" badge, "Activate" button
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, within, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// ---------------------------------------------------------------------------
// Mocks — must be defined BEFORE importing the component under test
// ---------------------------------------------------------------------------

// Mock react-router-dom
const mockNavigate = vi.fn();
vi.mock('react-router-dom', () => ({
  useNavigate: () => mockNavigate,
}));

// Mock auth store — default to superadmin
let mockRole = 'superadmin';
vi.mock('../../store/authStore', () => ({
  useAuthStore: (selector) => selector({ role: mockRole }),
}));

// Mock franchises API
const mockGetFranchises = vi.fn();
const mockToggleFranchiseStatus = vi.fn();
const mockDeleteFranchise = vi.fn();
const mockCreateFranchise = vi.fn();
const mockUpdateFranchise = vi.fn();
vi.mock('../../api/franchises', () => ({
  franchisesApi: {
    getFranchises: (...args) => mockGetFranchises(...args),
    toggleFranchiseStatus: (...args) => mockToggleFranchiseStatus(...args),
    deleteFranchise: (...args) => mockDeleteFranchise(...args),
    createFranchise: (...args) => mockCreateFranchise(...args),
    updateFranchise: (...args) => mockUpdateFranchise(...args),
  },
}));

// Mock react-i18next — returns the key as translation (transparent)
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key, opts) => {
      // For keys that include interpolation like {{name}}, include it
      if (opts?.name) return `${key} ${opts.name}`;
      if (opts?.count !== undefined) return `${opts.count} ${key}`;
      return key;
    },
    i18n: { language: 'en' },
  }),
}));

// Mock FranchiseFormModal — simple stub
vi.mock('./FranchiseFormModal', () => ({
  default: ({ franchise, onClose }) => (
    <div data-testid="franchise-form-modal">
      <span>{franchise ? 'Edit' : 'Create'}</span>
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));

// ---------------------------------------------------------------------------
// Import component under test AFTER mocks
// ---------------------------------------------------------------------------
import FranchisesPage from './FranchisesPage';

// ---------------------------------------------------------------------------
// Test data
// ---------------------------------------------------------------------------

const activeFranchise = {
  id: 1,
  name: 'SM Florida',
  country: 'USA',
  email: 'florida@sm.com',
  is_active: true,
  admins_count: 3,
  clients_count: 12,
  type: 'sm',
};

const inactiveFranchise = {
  id: 2,
  name: 'SM California',
  country: 'Mexico',
  email: 'california@sm.com',
  is_active: false,
  admins_count: 1,
  clients_count: 5,
  type: 'sm',
};

const thirdFranchise = {
  id: 3,
  name: 'SM Texas',
  country: 'USA',
  email: 'texas@sm.com',
  is_active: true,
  admins_count: 2,
  clients_count: 8,
  type: 'sm',
};

function allFranchises() {
  return [activeFranchise, inactiveFranchise, thirdFranchise];
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

beforeEach(() => {
  vi.clearAllMocks();
  mockRole = 'superadmin';
  mockGetFranchises.mockResolvedValue({
    data: allFranchises(),
    meta: { total: 3 },
  });
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('FranchisesPage', () => {
  // =========================================================================
  // Header
  // =========================================================================

  describe('Header', () => {
    it('renders the page title "Franchises"', async () => {
      render(<FranchisesPage />);
      expect(await screen.findByText('franchises.title')).toBeInTheDocument();
    });

    it('renders the total franchise counter', async () => {
      render(<FranchisesPage />);
      // The counter uses the "registered" translation key with count
      await waitFor(() => {
        expect(screen.getByText(/3.*franchises\.registered/)).toBeInTheDocument();
      });
    });

    it('renders the "Back" button', async () => {
      render(<FranchisesPage />);
      expect(await screen.findByText('common.back')).toBeInTheDocument();
    });

    it('"Back" button navigates back when clicked', async () => {
      render(<FranchisesPage />);
      const backBtn = await screen.findByText('common.back');
      fireEvent.click(backBtn);
      expect(mockNavigate).toHaveBeenCalledWith(-1);
    });

    it('renders the "+ New franchise" button for superadmin', async () => {
      render(<FranchisesPage />);
      expect(await screen.findByText('franchises.new')).toBeInTheDocument();
    });

    it('does NOT render the "+ New franchise" button for non-superadmin', async () => {
      mockRole = 'admin_sm';
      render(<FranchisesPage />);
      await waitFor(() => {
        expect(screen.queryByText('franchises.new')).not.toBeInTheDocument();
      });
    });
  });

  // =========================================================================
  // Search bar
  // =========================================================================

  describe('Search bar', () => {
    it('renders the search input with placeholder', async () => {
      render(<FranchisesPage />);
      expect(
        await screen.findByPlaceholderText('franchises.search_placeholder')
      ).toBeInTheDocument();
    });

    it('filters franchise cards by name in real time', async () => {
      const user = userEvent.setup();
      render(<FranchisesPage />);

      // Wait for cards to appear
      await screen.findByText('SM Florida');
      expect(screen.getByText('SM California')).toBeInTheDocument();
      expect(screen.getByText('SM Texas')).toBeInTheDocument();

      // Type a search term
      const input = screen.getByPlaceholderText('franchises.search_placeholder');
      await user.type(input, 'Florida');

      // Only matching card remains
      expect(screen.getByText('SM Florida')).toBeInTheDocument();
      expect(screen.queryByText('SM California')).not.toBeInTheDocument();
      expect(screen.queryByText('SM Texas')).not.toBeInTheDocument();
    });

    it('shows result count when filtering', async () => {
      const user = userEvent.setup();
      render(<FranchisesPage />);

      await screen.findByText('SM Florida');

      const input = screen.getByPlaceholderText('franchises.search_placeholder');
      await user.type(input, 'SM');

      // All 3 match "SM" — check results count is displayed
      await waitFor(() => {
        expect(screen.getByText(/franchises\.results/)).toBeInTheDocument();
      });
    });

    it('shows no-results message when search matches nothing', async () => {
      const user = userEvent.setup();
      render(<FranchisesPage />);

      await screen.findByText('SM Florida');

      const input = screen.getByPlaceholderText('franchises.search_placeholder');
      await user.type(input, 'NonExistent');

      await waitFor(() => {
        expect(screen.getByText('franchises.no_results')).toBeInTheDocument();
      });
    });

    it('clears search when the clear button is clicked', async () => {
      const user = userEvent.setup();
      render(<FranchisesPage />);

      await screen.findByText('SM Florida');

      const input = screen.getByPlaceholderText('franchises.search_placeholder');
      await user.type(input, 'Florida');

      // Only Florida visible
      expect(screen.queryByText('SM California')).not.toBeInTheDocument();

      // Click the clear button (the X button appears when search has text)
      // It's a button inside the search container — we can find the SVG-based button
      const searchContainer = input.parentElement;
      const clearButton = within(searchContainer).getAllByRole('button')[0];
      await user.click(clearButton);

      // All cards should reappear
      await waitFor(() => {
        expect(screen.getByText('SM California')).toBeInTheDocument();
        expect(screen.getByText('SM Texas')).toBeInTheDocument();
      });
    });
  });

  // =========================================================================
  // Franchise Card content
  // =========================================================================

  describe('Franchise Card', () => {
    it('displays franchise name on the card', async () => {
      render(<FranchisesPage />);
      expect(await screen.findByText('SM Florida')).toBeInTheDocument();
    });

    it('displays country on the card', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      // Multiple cards can share the same country (USA appears on Florida and Texas)
      const usaElements = screen.getAllByText('USA');
      expect(usaElements.length).toBeGreaterThanOrEqual(1);
      // Also check the unique country
      expect(screen.getByText('Mexico')).toBeInTheDocument();
    });

    it('displays avatar with initials from the franchise name', async () => {
      render(<FranchisesPage />);
      // "SM Florida" → initials "SF"
      expect(await screen.findByText('SF')).toBeInTheDocument();
    });

    it('displays email on the card', async () => {
      render(<FranchisesPage />);
      expect(await screen.findByText('florida@sm.com')).toBeInTheDocument();
    });

    it('displays Active badge for active franchise', async () => {
      render(<FranchisesPage />);
      const badges = await screen.findAllByText('franchises.active');
      expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('displays Inactive badge for inactive franchise', async () => {
      render(<FranchisesPage />);
      expect(await screen.findByText('franchises.inactive')).toBeInTheDocument();
    });

    it('displays admin count with label', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      // Admins count for SM Florida is 3
      expect(screen.getByText('3')).toBeInTheDocument();
      const adminLabels = screen.getAllByText('franchises.admins');
      expect(adminLabels.length).toBeGreaterThanOrEqual(1);
    });

    it('displays client count with label', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      // Clients count for SM Florida is 12
      expect(screen.getByText('12')).toBeInTheDocument();
      const clientLabels = screen.getAllByText('franchises.clients');
      expect(clientLabels.length).toBeGreaterThanOrEqual(1);
    });
  });

  // =========================================================================
  // Card action buttons
  // =========================================================================

  describe('Card buttons (superadmin)', () => {
    it('renders Edit button on each card', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      const editButtons = screen.getAllByText('common.edit');
      // 3 cards = 3 edit buttons
      expect(editButtons).toHaveLength(3);
    });

    it('renders Delete button on each card', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      const deleteButtons = screen.getAllByText('common.delete');
      expect(deleteButtons).toHaveLength(3);
    });

    it('renders "Deactivate" button for active franchise', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      const deactivateButtons = screen.getAllByText('franchises.deactivate');
      // 2 active franchises (SM Florida and SM Texas)
      expect(deactivateButtons).toHaveLength(2);
    });

    it('renders "Activate" button for inactive franchise', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');
      const activateButtons = screen.getAllByText('franchises.activate');
      // 1 inactive franchise (SM California)
      expect(activateButtons).toHaveLength(1);
    });

    it('does NOT render action buttons for non-superadmin', async () => {
      mockRole = 'admin_sm';
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');

      expect(screen.queryByText('common.edit')).not.toBeInTheDocument();
      expect(screen.queryByText('common.delete')).not.toBeInTheDocument();
      expect(screen.queryByText('franchises.deactivate')).not.toBeInTheDocument();
      expect(screen.queryByText('franchises.activate')).not.toBeInTheDocument();
    });
  });

  // =========================================================================
  // Inactive franchise visual state
  // =========================================================================

  describe('Inactive franchise visual state', () => {
    it('inactive card has desaturated styling class', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM California');

      // The inactive card wraps content in a div with saturate-[0.25] class
      const inactiveCardName = screen.getByText('SM California');
      // Traverse up to find the card container with the desaturation class
      const cardContent = inactiveCardName.closest('.saturate-\\[0\\.25\\]');
      expect(cardContent).not.toBeNull();
    });

    it('active card does NOT have desaturated styling', async () => {
      render(<FranchisesPage />);
      await screen.findByText('SM Florida');

      const activeCardName = screen.getByText('SM Florida');
      const cardContent = activeCardName.closest('.saturate-\\[0\\.25\\]');
      expect(cardContent).toBeNull();
    });
  });

  // =========================================================================
  // Loading & error states
  // =========================================================================

  describe('Loading & error states', () => {
    it('shows loading indicator while fetching', async () => {
      // Never resolve the promise
      mockGetFranchises.mockReturnValue(new Promise(() => { }));
      render(<FranchisesPage />);
      expect(screen.getByText('franchises.loading')).toBeInTheDocument();
    });

    it('shows error message on fetch failure', async () => {
      mockGetFranchises.mockRejectedValue(new Error('Network error'));
      render(<FranchisesPage />);
      expect(await screen.findByText('franchises.load_error')).toBeInTheDocument();
    });

    it('shows empty state when no franchises exist', async () => {
      mockGetFranchises.mockResolvedValue({ data: [], meta: { total: 0 } });
      render(<FranchisesPage />);
      expect(await screen.findByText('franchises.empty_title')).toBeInTheDocument();
    });
  });

  // =========================================================================
  // Modal interactions
  // =========================================================================

  describe('Modal interactions', () => {
    it('opens create modal when "+ New franchise" is clicked', async () => {
      const user = userEvent.setup();
      render(<FranchisesPage />);

      const newButton = await screen.findByText('franchises.new');
      await user.click(newButton);

      expect(screen.getByTestId('franchise-form-modal')).toBeInTheDocument();
      expect(screen.getByText('Create')).toBeInTheDocument();
    });

    it('opens edit modal when "Edit" is clicked on a card', async () => {
      const user = userEvent.setup();
      render(<FranchisesPage />);

      await screen.findByText('SM Florida');
      const editButtons = screen.getAllByText('common.edit');
      await user.click(editButtons[0]);

      expect(screen.getByTestId('franchise-form-modal')).toBeInTheDocument();
      expect(screen.getByText('Edit')).toBeInTheDocument();
    });
  });

  // =========================================================================
  // Delete action
  // =========================================================================

  describe('Delete action', () => {
    it('calls deleteFranchise API after confirmation', async () => {
      const user = userEvent.setup();
      vi.spyOn(window, 'confirm').mockReturnValue(true);
      mockDeleteFranchise.mockResolvedValue({});

      render(<FranchisesPage />);
      await screen.findByText('SM Florida');

      const deleteButtons = screen.getAllByText('common.delete');
      await user.click(deleteButtons[0]);

      expect(window.confirm).toHaveBeenCalled();
      await waitFor(() => {
        expect(mockDeleteFranchise).toHaveBeenCalledWith(activeFranchise.id);
      });

      window.confirm.mockRestore();
    });

    it('does NOT call deleteFranchise API when confirmation is cancelled', async () => {
      const user = userEvent.setup();
      vi.spyOn(window, 'confirm').mockReturnValue(false);

      render(<FranchisesPage />);
      await screen.findByText('SM Florida');

      const deleteButtons = screen.getAllByText('common.delete');
      await user.click(deleteButtons[0]);

      expect(mockDeleteFranchise).not.toHaveBeenCalled();

      window.confirm.mockRestore();
    });
  });

  // =========================================================================
  // Toggle status action
  // =========================================================================

  describe('Toggle status action', () => {
    it('calls toggleFranchiseStatus API after confirmation on deactivate', async () => {
      const user = userEvent.setup();
      vi.spyOn(window, 'confirm').mockReturnValue(true);
      mockToggleFranchiseStatus.mockResolvedValue({});

      render(<FranchisesPage />);
      await screen.findByText('SM Florida');

      const deactivateButtons = screen.getAllByText('franchises.deactivate');
      await user.click(deactivateButtons[0]);

      expect(window.confirm).toHaveBeenCalled();
      await waitFor(() => {
        expect(mockToggleFranchiseStatus).toHaveBeenCalledWith(activeFranchise.id);
      });

      window.confirm.mockRestore();
    });

    it('calls toggleFranchiseStatus API after confirmation on activate', async () => {
      const user = userEvent.setup();
      vi.spyOn(window, 'confirm').mockReturnValue(true);
      mockToggleFranchiseStatus.mockResolvedValue({});

      render(<FranchisesPage />);
      await screen.findByText('SM Florida');

      const activateButton = screen.getByText('franchises.activate');
      await user.click(activateButton);

      expect(window.confirm).toHaveBeenCalled();
      await waitFor(() => {
        expect(mockToggleFranchiseStatus).toHaveBeenCalledWith(inactiveFranchise.id);
      });

      window.confirm.mockRestore();
    });
  });
});
