<?php

namespace Tests\Feature;

use App\Enums\CatalogLevel;
use App\Enums\Role;
use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class CatalogItemTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Create a user with the superadmin Spatie role.
     */
    private function createSuperadmin(array $attributes = []): User
    {
        SpatieRole::firstOrCreate(['name' => Role::SUPERADMIN, 'guard_name' => 'web']);

        $user = User::factory()->create($attributes);
        $user->assignRole(Role::SUPERADMIN);

        return $user;
    }

    /**
     * Create a plain user with no roles assigned.
     */
    private function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create a bundle catalog item.
     */
    private function makeBundle(array $attributes = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'level' => CatalogLevel::Bundle->value,
            'name_es' => 'Bundle Test',
            'name_en' => 'Bundle Test',
            'order_index' => 0,
            'is_monthly' => false,
        ], $attributes));
    }

    /**
     * Create a service catalog item, optionally nested inside a bundle.
     */
    private function makeService(array $attributes = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'level' => CatalogLevel::Service->value,
            'name_es' => 'Service Test',
            'name_en' => 'Service Test',
            'order_index' => 0,
            'is_monthly' => false,
        ], $attributes));
    }

    /**
     * Create a deliverable catalog item nested inside a service.
     */
    private function makeDeliverable(CatalogItem $service, array $attributes = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'level' => CatalogLevel::Deliverable->value,
            'parent_id' => $service->id,
            'name_es' => 'Deliverable Test',
            'name_en' => 'Deliverable Test',
            'order_index' => 0,
            'is_monthly' => false,
            'estimated_hours' => 2.0,
        ], $attributes));
    }

    // ===========================================================================
    // GET /api/v1/catalog-items?level=  (index)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_catalog_items_index(): void
    {
        $response = $this->getJson('/api/v1/catalog-items?level=service');

        $response->assertStatus(401);
    }

    public function test_non_superadmin_gets_403_on_catalog_items_index(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/catalog-items?level=service');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_list_deliverables_by_level(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();
        $this->makeDeliverable($service, ['name_es' => 'Entregable A', 'name_en' => 'Deliverable A']);
        $this->makeDeliverable($service, ['name_es' => 'Entregable B', 'name_en' => 'Deliverable B']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items?level=deliverable');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'level',
                    'parent_id',
                    'name_es',
                    'name_en',
                    'is_monthly',
                    'order_index',
                    'estimated_hours',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_index_returns_only_items_matching_requested_level(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->makeBundle();
        $service = $this->makeService();
        $this->makeDeliverable($service);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items?level=service');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.level', CatalogLevel::Service->value);
    }

    public function test_index_returns_422_when_level_parameter_is_missing(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['level']);
    }

    public function test_index_returns_422_for_invalid_level_value(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items?level=invalid');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['level']);
    }

    // ===========================================================================
    // GET /api/v1/catalog-items/tree  (tree)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_catalog_tree(): void
    {
        $response = $this->getJson('/api/v1/catalog-items/tree');

        $response->assertStatus(401);
    }

    public function test_non_superadmin_gets_403_on_catalog_tree(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->getJson('/api/v1/catalog-items/tree');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_fetch_full_catalog_tree(): void
    {
        $superadmin = $this->createSuperadmin();
        $bundle = $this->makeBundle();
        $service = $this->makeService(['parent_id' => $bundle->id]);
        $this->makeDeliverable($service);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items/tree');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'bundles',
                'services',
                'counts' => ['bundles', 'services', 'deliverables'],
            ],
        ]);
        $response->assertJsonPath('success', true);
    }

    public function test_tree_counts_reflect_existing_items(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->makeBundle();
        $this->makeBundle(['name_es' => 'Bundle 2', 'name_en' => 'Bundle 2']);
        $service = $this->makeService();
        $this->makeDeliverable($service);
        $this->makeDeliverable($service, ['name_es' => 'D2', 'name_en' => 'D2']);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items/tree');

        $response->assertStatus(200);
        $response->assertJsonPath('data.counts.bundles', 2);
        $response->assertJsonPath('data.counts.services', 1);
        $response->assertJsonPath('data.counts.deliverables', 2);
    }

    public function test_tree_bundles_include_nested_services_and_deliverables(): void
    {
        $superadmin = $this->createSuperadmin();
        $bundle = $this->makeBundle();
        $service = $this->makeService(['parent_id' => $bundle->id]);
        $this->makeDeliverable($service);

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items/tree');

        $response->assertStatus(200);
        // The single bundle in data.bundles should have a children array with the service
        $this->assertCount(1, $response->json('data.bundles'));
        $this->assertNotEmpty($response->json('data.bundles.0.children'));
    }

    // ===========================================================================
    // POST /api/v1/catalog-items  (store)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_catalog_store(): void
    {
        $response = $this->postJson('/api/v1/catalog-items', [
            'level' => 'service',
            'name_es' => 'Nuevo Servicio',
            'name_en' => 'New Service',
        ]);

        $response->assertStatus(401);
    }

    public function test_non_superadmin_gets_403_on_catalog_store(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->postJson('/api/v1/catalog-items', [
            'level' => 'service',
            'name_es' => 'Nuevo Servicio',
            'name_en' => 'New Service',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_deliverable_with_parent_service(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();

        $payload = [
            'level' => 'deliverable',
            'parent_id' => $service->id,
            'name_es' => 'Nuevo Entregable',
            'name_en' => 'New Deliverable',
            'estimated_hours' => 4.5,
            'is_monthly' => false,
            'order_index' => 1,
        ];

        $response = $this->actingAs($superadmin)->postJson('/api/v1/catalog-items', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.level', 'deliverable');
        $response->assertJsonPath('data.parent_id', $service->id);
        $response->assertJsonPath('data.name_es', 'Nuevo Entregable');

        $this->assertDatabaseHas('catalog_items', [
            'level' => 'deliverable',
            'parent_id' => $service->id,
            'name_es' => 'Nuevo Entregable',
        ]);
    }

    public function test_store_returns_422_when_name_es_is_missing(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/catalog-items', [
            'level' => 'deliverable',
            'parent_id' => $service->id,
            'name_en' => 'New Deliverable',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name_es']);
    }

    public function test_store_returns_422_when_deliverable_has_no_parent_id(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/catalog-items', [
            'level' => 'deliverable',
            'name_es' => 'Entregable Huerfano',
            'name_en' => 'Orphan Deliverable',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_id']);
    }

    public function test_store_returns_422_when_bundle_receives_a_parent_id(): void
    {
        $superadmin = $this->createSuperadmin();
        $otherBundle = $this->makeBundle();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/catalog-items', [
            'level' => 'bundle',
            'name_es' => 'Bundle Anidado',
            'name_en' => 'Nested Bundle',
            'parent_id' => $otherBundle->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parent_id']);
    }

    public function test_store_creates_service_and_persists_to_database(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->postJson('/api/v1/catalog-items', [
            'level' => 'service',
            'name_es' => 'Servicio Nuevo',
            'name_en' => 'New Service',
            'is_monthly' => true,
            'order_index' => 0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('catalog_items', [
            'level' => 'service',
            'name_es' => 'Servicio Nuevo',
            'is_monthly' => true,
        ]);
    }

    // ===========================================================================
    // GET /api/v1/catalog-items/{id}  (show)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_catalog_show(): void
    {
        $service = $this->makeService();

        $response = $this->getJson("/api/v1/catalog-items/{$service->id}");

        $response->assertStatus(401);
    }

    public function test_superadmin_can_show_service_with_children_loaded(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService(['name_es' => 'Mi Servicio', 'name_en' => 'My Service']);
        $this->makeDeliverable($service, ['name_es' => 'Entregable 1', 'name_en' => 'Deliverable 1']);
        $this->makeDeliverable($service, ['name_es' => 'Entregable 2', 'name_en' => 'Deliverable 2']);

        $response = $this->actingAs($superadmin)->getJson("/api/v1/catalog-items/{$service->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.level', 'service');
        $response->assertJsonPath('data.name_es', 'Mi Servicio');
        $this->assertCount(2, $response->json('data.children'));
    }

    public function test_superadmin_can_show_deliverable_with_parent_loaded(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService(['name_es' => 'Padre', 'name_en' => 'Parent']);
        $deliverable = $this->makeDeliverable($service, ['name_es' => 'Hijo', 'name_en' => 'Child']);

        $response = $this->actingAs($superadmin)->getJson("/api/v1/catalog-items/{$deliverable->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.level', 'deliverable');
        $response->assertJsonPath('data.parent.id', $service->id);
    }

    public function test_show_returns_404_for_nonexistent_id(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->getJson('/api/v1/catalog-items/999999');

        $response->assertStatus(404);
    }

    // ===========================================================================
    // PATCH /api/v1/catalog-items/{id}  (update)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_catalog_update(): void
    {
        $service = $this->makeService();

        $response = $this->patchJson("/api/v1/catalog-items/{$service->id}", [
            'name_es' => 'Nombre Actualizado',
        ]);

        $response->assertStatus(401);
    }

    public function test_non_superadmin_gets_403_on_catalog_update(): void
    {
        $user = $this->createRegularUser();
        $service = $this->makeService();

        $response = $this->actingAs($user)->patchJson("/api/v1/catalog-items/{$service->id}", [
            'name_es' => 'Nombre Actualizado',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_update_name_es_of_catalog_item(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService(['name_es' => 'Nombre Original', 'name_en' => 'Original Name']);

        $response = $this->actingAs($superadmin)->patchJson("/api/v1/catalog-items/{$service->id}", [
            'name_es' => 'Nombre Actualizado',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name_es', 'Nombre Actualizado');

        $this->assertDatabaseHas('catalog_items', [
            'id' => $service->id,
            'name_es' => 'Nombre Actualizado',
        ]);
    }

    public function test_update_can_move_deliverable_to_another_service_via_parent_id(): void
    {
        $superadmin = $this->createSuperadmin();
        $serviceA = $this->makeService(['name_es' => 'Servicio A', 'name_en' => 'Service A']);
        $serviceB = $this->makeService(['name_es' => 'Servicio B', 'name_en' => 'Service B']);
        $deliverable = $this->makeDeliverable($serviceA);

        $this->assertDatabaseHas('catalog_items', ['id' => $deliverable->id, 'parent_id' => $serviceA->id]);

        $response = $this->actingAs($superadmin)->patchJson("/api/v1/catalog-items/{$deliverable->id}", [
            'parent_id' => $serviceB->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.parent_id', $serviceB->id);

        $this->assertDatabaseHas('catalog_items', [
            'id' => $deliverable->id,
            'parent_id' => $serviceB->id,
        ]);
    }

    public function test_update_persists_estimated_hours_change(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();
        $deliverable = $this->makeDeliverable($service, ['estimated_hours' => 2.0]);

        $response = $this->actingAs($superadmin)->patchJson("/api/v1/catalog-items/{$deliverable->id}", [
            'estimated_hours' => 8.0,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.estimated_hours', 8);

        $this->assertDatabaseHas('catalog_items', [
            'id' => $deliverable->id,
            'estimated_hours' => 8.0,
        ]);
    }

    // ===========================================================================
    // DELETE /api/v1/catalog-items/{id}  (destroy)
    // ===========================================================================

    public function test_unauthenticated_user_gets_401_on_catalog_delete(): void
    {
        $service = $this->makeService();

        $response = $this->deleteJson("/api/v1/catalog-items/{$service->id}");

        $response->assertStatus(401);
    }

    public function test_non_superadmin_gets_403_on_catalog_delete(): void
    {
        $user = $this->createRegularUser();
        $service = $this->makeService();

        $response = $this->actingAs($user)->deleteJson("/api/v1/catalog-items/{$service->id}");

        $response->assertStatus(403);
    }

    public function test_superadmin_can_delete_deliverable(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();
        $deliverable = $this->makeDeliverable($service);
        $deliverableId = $deliverable->id;

        $response = $this->actingAs($superadmin)->deleteJson("/api/v1/catalog-items/{$deliverableId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('catalog_items', ['id' => $deliverableId]);
    }

    public function test_deleting_service_without_cascade_orphans_its_deliverables(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();
        $deliverable = $this->makeDeliverable($service);
        $serviceId = $service->id;

        $response = $this->actingAs($superadmin)->deleteJson("/api/v1/catalog-items/{$serviceId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('catalog_items', ['id' => $serviceId]);
        // Deliverable must still exist but with parent_id nulled out
        $this->assertDatabaseHas('catalog_items', ['id' => $deliverable->id, 'parent_id' => null]);
    }

    public function test_deleting_service_with_cascade_removes_its_deliverables(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();
        $deliverableA = $this->makeDeliverable($service, ['name_es' => 'D1', 'name_en' => 'D1']);
        $deliverableB = $this->makeDeliverable($service, ['name_es' => 'D2', 'name_en' => 'D2']);
        $serviceId = $service->id;

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/catalog-items/{$serviceId}?cascade_children=1");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('catalog_items', ['id' => $serviceId]);
        $this->assertDatabaseMissing('catalog_items', ['id' => $deliverableA->id]);
        $this->assertDatabaseMissing('catalog_items', ['id' => $deliverableB->id]);
    }

    public function test_cascade_children_on_deliverable_returns_422(): void
    {
        $superadmin = $this->createSuperadmin();
        $service = $this->makeService();
        $deliverable = $this->makeDeliverable($service);

        $response = $this->actingAs($superadmin)
            ->deleteJson("/api/v1/catalog-items/{$deliverable->id}?cascade_children=1");

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'catalog.cascade_children_service_only');
    }

    public function test_delete_returns_404_for_nonexistent_id(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->deleteJson('/api/v1/catalog-items/999999');

        $response->assertStatus(404);
    }
}
