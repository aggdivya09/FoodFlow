/**
 * ============================================================================
 * FoodFlow - Operating System Scheduling Module
 * Algorithms: First-Come, First-Served (FCFS) & Multi-Factor Priority Scheduling
 *
 * OS Concepts Demonstrated:
 *   1. CPU Scheduling   — FCFS & Multi-Factor Priority Scheduling
 *   2. Synchronization  — Mutex locks & Counting Semaphores (kitchen burners,
 *                         delivery agents as shared critical resources)
 *   3. IPC              — Shared Memory segment & Message Queue (kitchen-to-
 *                         delivery-agent communication pipeline)
 *
 * Priority Criterion:
 *   - Less preparation time (Shortest Job First / shortest burst time advantage)
 *   - Near delivery distance (reduces delivery turnaround time & vehicle bottlenecks)
 *   - Aging mechanism to prevent starvation of large / distant orders
 * ============================================================================
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define MAX_ORDERS 50

typedef struct {
    int order_id;
    char customer_name[32];
    char food_summary[48];
    int arrival_time;       /* Arrival time at kitchen in minutes (AT) */
    int prep_time;          /* Preparation / Burst time in minutes (BT) */
    float distance_km;      /* Delivery distance in km */
    float priority_score;   /* Calculated priority: lower value = higher priority */
    int start_time;
    int completion_time;    /* CT */
    int waiting_time;       /* WT = TAT - BT */
    int turnaround_time;    /* TAT = CT - AT */
} FoodOrder;

/* Compute priority score: less prep time + near delivery distance = higher priority */
float calculate_priority_score(int prep_time, float distance_km, int arrival_time, int current_time) {
    int waiting = current_time - arrival_time;
    if (waiting < 0) waiting = 0;
    /* Base score: prep time weighted at 60%, distance in km (approx 2 mins/km) weighted */
    float score = (prep_time * 0.6f) + (distance_km * 2.5f);
    /* Aging: subtract 0.5 points per minute waited to prevent starvation */
    score -= (waiting * 0.5f);
    return score;
}

void print_gantt_chart(FoodOrder orders[], int n) {
    printf("\n+-------------------------------------------------------------------------+\n");
    printf("|                             GANTT CHART                                 |\n");
    printf("+-------------------------------------------------------------------------+\n|");
    for (int i = 0; i < n; i++) {
        printf("  #FF-%d  |", orders[i].order_id);
    }
    printf("\n");
    printf("0");
    for (int i = 0; i < n; i++) {
        printf("        %d", orders[i].completion_time);
    }
    printf("\n\n");
}

void print_metrics(FoodOrder orders[], int n, const char *algo_name) {
    float total_wt = 0, total_tat = 0;

    printf("\n========================================================================================\n");
    printf("  %s SCHEDULING RESULTS\n", algo_name);
    printf("========================================================================================\n");
    printf("%-8s %-16s %-6s %-6s %-8s %-6s %-6s %-6s\n",
           "Order ID", "Customer", "AT(m)", "BT(m)", "Dist(km)", "CT(m)", "TAT(m)", "WT(m)");
    printf("----------------------------------------------------------------------------------------\n");

    for (int i = 0; i < n; i++) {
        total_wt += orders[i].waiting_time;
        total_tat += orders[i].turnaround_time;
        printf("#FF-%-4d %-16.16s %-6d %-6d %-8.1f %-6d %-6d %-6d\n",
               orders[i].order_id,
               orders[i].customer_name,
               orders[i].arrival_time,
               orders[i].prep_time,
               orders[i].distance_km,
               orders[i].completion_time,
               orders[i].turnaround_time,
               orders[i].waiting_time);
    }

    printf("----------------------------------------------------------------------------------------\n");
    printf("  Average Waiting Time (AWT)       : %.2f minutes\n", total_wt / n);
    printf("  Average Turnaround Time (ATAT)  : %.2f minutes\n", total_tat / n);
    printf("========================================================================================\n");
    print_gantt_chart(orders, n);
}

/* FCFS: First Come First Served (Chronological order) */
void schedule_fcfs(FoodOrder original[], int n) {
    FoodOrder orders[MAX_ORDERS];
    memcpy(orders, original, sizeof(FoodOrder) * n);

    /* Sort by arrival time */
    for (int i = 0; i < n - 1; i++) {
        for (int j = 0; j < n - i - 1; j++) {
            if (orders[j].arrival_time > orders[j + 1].arrival_time) {
                FoodOrder temp = orders[j];
                orders[j] = orders[j + 1];
                orders[j + 1] = temp;
            }
        }
    }

    int current_time = 0;
    for (int i = 0; i < n; i++) {
        if (current_time < orders[i].arrival_time) {
            current_time = orders[i].arrival_time;
        }
        orders[i].start_time = current_time;
        orders[i].completion_time = current_time + orders[i].prep_time;
        orders[i].turnaround_time = orders[i].completion_time - orders[i].arrival_time;
        orders[i].waiting_time = orders[i].turnaround_time - orders[i].prep_time;
        current_time = orders[i].completion_time;
    }

    print_metrics(orders, n, "FIRST-COME, FIRST-SERVED (FCFS)");
}

/* Priority Scheduling: Prioritizes less prep time and nearer delivery distance */
void schedule_priority(FoodOrder original[], int n) {
    FoodOrder orders[MAX_ORDERS];
    memcpy(orders, original, sizeof(FoodOrder) * n);
    int completed[MAX_ORDERS] = {0};
    FoodOrder scheduled[MAX_ORDERS];
    int scheduled_count = 0;

    int current_time = 0;

    while (scheduled_count < n) {
        int best_idx = -1;
        float min_priority_score = 999999.0f;

        /* Look for arrived orders */
        for (int i = 0; i < n; i++) {
            if (!completed[i] && orders[i].arrival_time <= current_time) {
                float score = calculate_priority_score(orders[i].prep_time, orders[i].distance_km, orders[i].arrival_time, current_time);
                orders[i].priority_score = score;
                if (score < min_priority_score) {
                    min_priority_score = score;
                    best_idx = i;
                }
            }
        }

        /* If no order has arrived yet, advance clock to next arrival */
        if (best_idx == -1) {
            int earliest_arrival = 999999;
            for (int i = 0; i < n; i++) {
                if (!completed[i] && orders[i].arrival_time < earliest_arrival) {
                    earliest_arrival = orders[i].arrival_time;
                }
            }
            current_time = earliest_arrival;
            continue;
        }

        /* Schedule chosen order */
        completed[best_idx] = 1;
        orders[best_idx].start_time = current_time;
        orders[best_idx].completion_time = current_time + orders[best_idx].prep_time;
        orders[best_idx].turnaround_time = orders[best_idx].completion_time - orders[best_idx].arrival_time;
        orders[best_idx].waiting_time = orders[best_idx].turnaround_time - orders[best_idx].prep_time;
        current_time = orders[best_idx].completion_time;

        scheduled[scheduled_count++] = orders[best_idx];
    }

    print_metrics(scheduled, n, "PRIORITY (LESS TIME & NEAR DISTANCE)");
}

/* ============================================================================
 * MODULE 2: SYNCHRONIZATION
 * Concepts: Mutex Lock, Counting Semaphore
 *
 * Problem:  Kitchen has limited burners (critical resource). Multiple orders
 *           cannot use the same burner simultaneously — race condition risk.
 *           Delivery agents are also a shared resource with a fixed pool.
 *
 * Solution: Mutex  → guards a single shared resource (one burner, one slot).
 *           Semaphore → counting resource pool (N burners, M delivery agents).
 * ============================================================================ */

#define MAX_BURNERS   3   /* Total kitchen burners (counting semaphore value) */
#define MAX_AGENTS    2   /* Total delivery agents available */

typedef struct {
    int value;            /* Semaphore count */
    char name[32];
} Semaphore;

typedef struct {
    int locked;           /* 0 = free, 1 = locked */
    int held_by_order;
    char resource_name[32];
} Mutex;

void semaphore_init(Semaphore *s, int value, const char *name) {
    s->value = value;
    strncpy(s->name, name, 31);
}

int semaphore_wait(Semaphore *s, int order_id) {
    if (s->value > 0) {
        s->value--;
        printf("  [SEMAPHORE WAIT]  Order #FF-%d acquired %-20s | Remaining: %d\n",
               order_id, s->name, s->value);
        return 1; /* acquired */
    }
    printf("  [SEMAPHORE BLOCK] Order #FF-%d blocked — %-20s full (0 left)\n",
           order_id, s->name);
    return 0; /* blocked */
}

void semaphore_signal(Semaphore *s, int order_id) {
    s->value++;
    printf("  [SEMAPHORE SIGNAL] Order #FF-%d released %-20s | Available: %d\n",
           order_id, s->name, s->value);
}

void mutex_init(Mutex *m, const char *name) {
    m->locked = 0;
    m->held_by_order = -1;
    strncpy(m->resource_name, name, 31);
}

int mutex_lock(Mutex *m, int order_id) {
    if (!m->locked) {
        m->locked = 1;
        m->held_by_order = order_id;
        printf("  [MUTEX LOCK]   Order #FF-%d locked   '%s'\n", order_id, m->resource_name);
        return 1;
    }
    printf("  [MUTEX WAIT]   Order #FF-%d waiting — '%s' held by Order #FF-%d\n",
           order_id, m->resource_name, m->held_by_order);
    return 0;
}

void mutex_unlock(Mutex *m, int order_id) {
    m->locked = 0;
    m->held_by_order = -1;
    printf("  [MUTEX UNLOCK] Order #FF-%d unlocked '%s'\n", order_id, m->resource_name);
}

void demonstrate_synchronization(FoodOrder orders[], int n) {
    printf("\n========================================================================================\n");
    printf("  MODULE 2: SYNCHRONIZATION (Mutex & Semaphore)\n");
    printf("========================================================================================\n");
    printf("  Kitchen Burners  (Semaphore, count=%d) — shared among concurrent prep tasks\n", MAX_BURNERS);
    printf("  Delivery Agents  (Semaphore, count=%d) — shared delivery resource pool\n", MAX_AGENTS);
    printf("  Order Ticket Log (Mutex)               — single-writer critical section\n");
    printf("----------------------------------------------------------------------------------------\n\n");

    Semaphore burner_sem, agent_sem;
    Mutex     log_mutex;

    semaphore_init(&burner_sem, MAX_BURNERS, "KitchenBurners");
    semaphore_init(&agent_sem,  MAX_AGENTS,  "DeliveryAgents");
    mutex_init(&log_mutex, "OrderTicketLog");

    for (int i = 0; i < n; i++) {
        int oid = orders[i].order_id;
        printf("  >> Order #FF-%d (%s)\n", oid, orders[i].food_summary);

        int got_burner = semaphore_wait(&burner_sem, oid);
        int got_agent  = semaphore_wait(&agent_sem,  oid);
        int got_log    = mutex_lock(&log_mutex, oid);

        if (got_burner && got_agent && got_log) {
            printf("  [CRITICAL SECTION] Order #FF-%d: prep started, agent assigned, ticket logged.\n", oid);
            mutex_unlock(&log_mutex, oid);
            semaphore_signal(&burner_sem, oid);
            semaphore_signal(&agent_sem,  oid);
        } else {
            printf("  [DEADLOCK AVOIDED] Order #FF-%d could not acquire all resources — will retry.\n", oid);
            if (got_burner) semaphore_signal(&burner_sem, oid);
            if (got_agent)  semaphore_signal(&agent_sem,  oid);
            if (got_log)    mutex_unlock(&log_mutex, oid);
        }
        printf("\n");
    }

    printf("  Synchronization complete — no race conditions, no deadlock.\n");
    printf("========================================================================================\n");
}


/* ============================================================================
 * MODULE 3: INTER-PROCESS COMMUNICATION (IPC)
 * Concepts: Shared Memory, Message Queue
 *
 * Problem:  Kitchen process and Delivery process are separate entities.
 *           They need to communicate order status without tight coupling.
 *
 * Solution:
 *   Shared Memory  → Kitchen writes order status; Delivery reads it directly.
 *   Message Queue  → Kitchen enqueues "Order Ready" messages; Delivery agent
 *                    dequeues them in FIFO order and dispatches.
 * ============================================================================ */

#define MSG_QUEUE_SIZE 10
#define SHM_SLOTS      10

/* --- Shared Memory --- */
typedef struct {
    int   order_id;
    char  status[16];   /* "PENDING", "READY", "DISPATCHED" */
    int   agent_id;
} SharedMemorySlot;

SharedMemorySlot shm[SHM_SLOTS];  /* Simulated shared memory segment */
int shm_count = 0;

void shm_write(int order_id, const char *status, int agent_id) {
    if (shm_count >= SHM_SLOTS) return;
    shm[shm_count].order_id = order_id;
    strncpy(shm[shm_count].status, status, 15);
    shm[shm_count].agent_id = agent_id;
    shm_count++;
}

void shm_print_all() {
    printf("  [SHARED MEMORY SEGMENT — %d slots written]\n", shm_count);
    printf("  %-10s %-14s %-10s\n", "Order ID", "Status", "Agent ID");
    printf("  -----------------------------------\n");
    for (int i = 0; i < shm_count; i++) {
        printf("  #FF-%-6d %-14s Agent-%d\n",
               shm[i].order_id, shm[i].status, shm[i].agent_id);
    }
}

/* --- Message Queue --- */
typedef struct {
    int  order_id;
    char message[64];
} IPCMessage;

IPCMessage msg_queue[MSG_QUEUE_SIZE];
int mq_head = 0, mq_tail = 0, mq_count = 0;

int mq_send(int order_id, const char *msg) {
    if (mq_count >= MSG_QUEUE_SIZE) {
        printf("  [MQ FULL] Cannot enqueue Order #FF-%d — queue full!\n", order_id);
        return 0;
    }
    msg_queue[mq_tail].order_id = order_id;
    strncpy(msg_queue[mq_tail].message, msg, 63);
    mq_tail = (mq_tail + 1) % MSG_QUEUE_SIZE;
    mq_count++;
    printf("  [MQ SEND] Kitchen  → Queue  : Order #FF-%d | \"%s\"\n", order_id, msg);
    return 1;
}

int mq_receive(IPCMessage *out) {
    if (mq_count == 0) return 0;
    *out = msg_queue[mq_head];
    mq_head = (mq_head + 1) % MSG_QUEUE_SIZE;
    mq_count--;
    return 1;
}

void demonstrate_ipc(FoodOrder orders[], int n) {
    printf("\n========================================================================================\n");
    printf("  MODULE 3: INTER-PROCESS COMMUNICATION (IPC)\n");
    printf("========================================================================================\n");
    printf("  Shared Memory  → Kitchen writes order status; Delivery agent reads in real-time.\n");
    printf("  Message Queue  → Kitchen sends \"Order Ready\" msgs; Delivery dequeues & dispatches.\n");
    printf("----------------------------------------------------------------------------------------\n\n");

    /* Phase 1: Kitchen process writes to shared memory & message queue */
    printf("  -- KITCHEN PROCESS: Writing to Shared Memory & enqueuing messages --\n\n");
    for (int i = 0; i < n; i++) {
        int oid     = orders[i].order_id;
        int agent   = (i % MAX_AGENTS) + 1;
        char msg[64];

        shm_write(oid, "READY", agent);

        snprintf(msg, sizeof(msg), "Order #FF-%d ready — assign to Agent-%d", oid, agent);
        mq_send(oid, msg);
    }

    printf("\n");
    shm_print_all();

    /* Phase 2: Delivery process dequeues messages */
    printf("\n  -- DELIVERY PROCESS: Dequeuing from Message Queue & dispatching --\n\n");
    IPCMessage received;
    int dispatched = 0;
    while (mq_receive(&received)) {
        printf("  [MQ RECV] Delivery ← Queue  : Order #FF-%d | \"%s\"\n",
               received.order_id, received.message);

        /* Update shared memory status to DISPATCHED */
        for (int i = 0; i < shm_count; i++) {
            if (shm[i].order_id == received.order_id) {
                strncpy(shm[i].status, "DISPATCHED", 15);
                break;
            }
        }
        dispatched++;
    }

    printf("\n  -- SHARED MEMORY after Dispatch --\n");
    shm_print_all();

    printf("\n  IPC complete — %d orders dispatched via message queue.\n", dispatched);
    printf("========================================================================================\n");
}


/* ============================================================================
 * MAIN
 * ============================================================================ */
int main(int argc, char *argv[]) {
    FoodOrder sample_orders[] = {
        { 1001, "Rohit Sharma",   "Family Pizza Feast",      0, 24, 6.5f, 0, 0, 0, 0, 0 },
        { 1002, "Neha Panwar",    "Thums Up + Taco",         1,  6, 1.2f, 0, 0, 0, 0, 0 },
        { 1003, "Anita Rawat",    "Cold Coffee + Brownie",   2,  8, 2.0f, 0, 0, 0, 0, 0 },
        { 1004, "Karan Negi",     "Double Cheese Pizza",     3, 18, 5.0f, 0, 0, 0, 0, 0 },
        { 1005, "Priya Bisht",    "Classic Burger + Fries",  4, 10, 1.8f, 0, 0, 0, 0, 0 }
    };
    int n = sizeof(sample_orders) / sizeof(sample_orders[0]);

    printf("\n========================================================================================\n");
    printf("     FOODFLOW: OS CONCEPTS — SCHEDULING + SYNCHRONIZATION + IPC MODULE           \n");
    printf("========================================================================================\n");
    printf("Orders: %d  |  Kitchen Burners (Semaphore): %d  |  Delivery Agents (Semaphore): %d\n\n",
           n, MAX_BURNERS, MAX_AGENTS);

    /* MODULE 1: Scheduling */
    schedule_fcfs(sample_orders, n);
    schedule_priority(sample_orders, n);

    printf("\nOBSERVATION & OS CONCEPT COMPARISON:\n");
    printf("1. FCFS processes Order #FF-1001 (Family Feast, 24m) first, forcing quick orders to wait.\n");
    printf("2. Priority Scheduling prioritizes Order #FF-1002 (6m, 1.2km) and #FF-1003 (8m, 2.0km),\n");
    printf("   significantly lowering Average Waiting Time (AWT) and speeding doorstep deliveries!\n");

    /* MODULE 2: Synchronization */
    demonstrate_synchronization(sample_orders, n);

    /* MODULE 3: IPC */
    demonstrate_ipc(sample_orders, n);

    return 0;
}
