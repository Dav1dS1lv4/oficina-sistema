import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MaintenanceList } from './maintenance-list';

describe('MaintenanceList', () => {
  let component: MaintenanceList;
  let fixture: ComponentFixture<MaintenanceList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MaintenanceList],
    }).compileComponents();

    fixture = TestBed.createComponent(MaintenanceList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
